<?php

namespace App\Services;

use App\Enums\RegleValorisationNature;
use App\Enums\StatutPret;
use App\Enums\TypeRemboursement;
use App\Exceptions\OperationRefusee;
use App\Models\Achat;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Remboursement;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Remboursements de prêts (registre immuable 🔒), en argent ou en kilos.
 *
 * - Restant dû = remis − Σ remboursements, jamais négatif (invariant 1) : un
 *   remboursement qui le dépasserait est refusé (un trop-perçu serait un crédit
 *   producteur explicite, pas encore prévu).
 * - En kilos : valorisés selon la règle choisie par la direction (question 3), figée
 *   sur la ligne ; sans règle choisie, refus.
 * - Soldé quand tout a été remis et tout remboursé ; une contre-passation rouvre.
 */
class Remboursements
{
    /** Prix au kilo appliqué aux kilos livrés sur ce prêt, selon la règle choisie. */
    public static function prixNature(Pret $pret, int $prixAchatKg): int
    {
        $regle = Parametre::regleRemboursementNature();

        return match ($regle) {
            null => throw new OperationRefusee('La direction n\'a pas encore choisi comment valoriser les kilos livrés en remboursement '
                .'(Paramètres, question 3) : cet achat ne peut pas rembourser le prêt pour l\'instant.'),
            RegleValorisationNature::PrixAchat => $prixAchatKg,
            RegleValorisationNature::PrixReferencePret => $pret->prix_reference_kg_fcfa
                ?? throw new OperationRefusee("Le prêt {$pret->reference} n'a pas de prix de référence : la règle choisie ne peut pas s'appliquer."),
        };
    }

    /** Kilos livrés retenus sur le prêt : appelé par App\Services\Achats, dans sa transaction. */
    public static function nature(Pret $pret, Achat $achat, int $grammes, User $auteur): Remboursement
    {
        return DB::transaction(function () use ($pret, $achat, $grammes, $auteur) {
            $pret = Pret::query()->lockForUpdate()->findOrFail($pret->id);
            $prix = self::prixNature($pret, $achat->prix_kg_fcfa);
            // Arrondi au franc le plus proche, une seule fois ; plafonné au restant dû.
            $valeur = min(intdiv($grammes * $prix + 500, 1000), $pret->restantDu());
            if ($valeur <= 0) {
                throw new OperationRefusee("Le prêt {$pret->reference} n'a plus rien à rembourser.");
            }

            $remboursement = Remboursement::query()->create([
                'pret_id' => $pret->id,
                'type' => TypeRemboursement::Nature,
                'montant_fcfa' => $valeur,
                'grammes' => $grammes,
                'prix_kg_fcfa' => $prix,
                'regle_valorisation' => Parametre::regleRemboursementNature(),
                'achat_id' => $achat->id,
                'date_remboursement' => Carbon::parse($achat->date_achat)->toDateString(),
                'cree_par' => $auteur->id,
            ]);

            self::mettreAJourStatut($pret);

            return $remboursement;
        });
    }

    public static function especes(Pret $pret, CompteTresorerie $compte, int $montant, Carbon $date, User $auteur, ?string $reference = null): Remboursement
    {
        if (! $auteur->can('encaisser-remboursements')) {
            throw new OperationRefusee('Votre rôle ne permet pas d\'encaisser un remboursement.');
        }

        return DB::transaction(function () use ($pret, $compte, $montant, $date, $auteur, $reference) {
            $pret = Pret::query()->lockForUpdate()->findOrFail($pret->id);
            $restant = $pret->restantDu();
            if ($montant <= 0 || $montant > $restant) {
                throw new OperationRefusee('Le montant remboursé doit être compris entre 1 et '.Format::fcfa($restant).' (restant dû).');
            }

            $mouvement = Tresorerie::encaisserRemboursement($pret, $compte, $montant, $date, $auteur, $reference);

            $remboursement = Remboursement::query()->create([
                'pret_id' => $pret->id,
                'type' => TypeRemboursement::Especes,
                'montant_fcfa' => $montant,
                'mouvement_id' => $mouvement->id,
                'date_remboursement' => $date->toDateString(),
                'cree_par' => $auteur->id,
            ]);

            self::mettreAJourStatut($pret);

            return $remboursement;
        });
    }

    /**
     * Annule un remboursement par une ligne négative ; en espèces, l'argent ressort aussi
     * de la trésorerie. Un remboursement en kilos ne s'annule pas ici : il suit l'achat.
     */
    public static function contrePasser(Remboursement $remboursement, string $motif, User $auteur): Remboursement
    {
        if (! $auteur->can('encaisser-remboursements')) {
            throw new OperationRefusee('Votre rôle ne permet pas de corriger un remboursement.');
        }
        if (mb_strlen(trim($motif)) < 5) {
            throw new OperationRefusee('Le motif est obligatoire (5 caractères au moins).');
        }

        return DB::transaction(function () use ($remboursement, $motif, $auteur) {
            $original = Remboursement::query()->lockForUpdate()->findOrFail($remboursement->id);
            if ($original->type === TypeRemboursement::ContrePassation) {
                throw new OperationRefusee('Une contre-passation ne se contre-passe pas.');
            }
            if ($original->type === TypeRemboursement::Nature) {
                throw new OperationRefusee('Un remboursement en kilos suit son achat : il ne se corrige pas seul.');
            }
            if (Remboursement::query()->where('annule_id', $original->id)->exists()) {
                throw new OperationRefusee('Ce remboursement a déjà été contre-passé.');
            }

            $mouvementInverse = null;
            if ($original->mouvement_id !== null) {
                $mouvementInverse = Tresorerie::contrePasser(
                    MouvementTresorerie::query()->findOrFail($original->mouvement_id), trim($motif), $auteur, depuisOrigine: true,
                )[0];
            }

            $inverse = Remboursement::query()->create([
                'pret_id' => $original->pret_id,
                'type' => TypeRemboursement::ContrePassation,
                'montant_fcfa' => -$original->montant_fcfa,
                'mouvement_id' => $mouvementInverse?->id,
                'date_remboursement' => Carbon::today()->toDateString(),
                'motif' => trim($motif),
                'annule_id' => $original->id,
                'cree_par' => $auteur->id,
            ]);

            self::mettreAJourStatut(Pret::query()->findOrFail($original->pret_id));

            return $inverse;
        });
    }

    /** Soldé quand tout est remis et tout est remboursé ; sinon retour à l'état d'avant. */
    public static function mettreAJourStatut(Pret $pret): void
    {
        $solde = $pret->resteARemettre() === 0 && $pret->restantDu() === 0;

        if ($solde && $pret->statut === StatutPret::Decaisse) {
            $pret->update(['statut' => StatutPret::Solde]);
        } elseif (! $solde && $pret->statut === StatutPret::Solde) {
            $pret->update(['statut' => $pret->resteARemettre() === 0 ? StatutPret::Decaisse : StatutPret::Valide]);
        }
    }
}
