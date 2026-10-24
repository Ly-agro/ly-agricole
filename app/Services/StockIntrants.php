<?php

namespace App\Services;

use App\Enums\StatutPret;
use App\Enums\TypeMouvementIntrant;
use App\Exceptions\OperationRefusee;
use App\Models\Intrant;
use App\Models\Magasin;
use App\Models\MouvementIntrant;
use App\Models\Pret;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seule porte d'entrée des mouvements d'intrants (registre immuable 🔒).
 *
 * - Stock d'un intrant dans un magasin = Σ quantités ≥ 0 ; une sortie qui le rendrait
 *   négatif est refusée. L'intrant est verrouillé pendant l'écriture.
 * - Distribution à crédit : rattachée à un prêt validé en intrants ou mixte ; sa
 *   valeur (quantité × prix du jour) est figée et compte dans ce qui est remis au
 *   producteur ; jamais au-delà du montant du prêt.
 * - Une erreur se corrige par contre-passation motivée.
 */
class StockIntrants
{
    public const QUANTITE_MAX = 1_000_000;

    public static function entree(Intrant $intrant, Magasin $magasin, int $quantite, Carbon $date, User $auteur, ?string $motif = null): MouvementIntrant
    {
        self::verifierDroit($auteur, 'gerer-intrants');

        return DB::transaction(fn () => self::ecrire(
            self::verrouiller($intrant), $magasin, TypeMouvementIntrant::Entree, $quantite, $date, $auteur, motif: $motif,
        ));
    }

    /** Perte (quantité retirée) ou ajustement d'inventaire (quantité signée), motif obligatoire. */
    public static function corriger(Intrant $intrant, Magasin $magasin, TypeMouvementIntrant $type, int $quantiteSignee, Carbon $date, string $motif, User $auteur): MouvementIntrant
    {
        self::verifierDroit($auteur, 'gerer-intrants');
        if (! in_array($type, [TypeMouvementIntrant::Perte, TypeMouvementIntrant::Ajustement], true)) {
            throw new OperationRefusee('Seules une perte ou un ajustement se saisissent ici.');
        }
        if ($type === TypeMouvementIntrant::Perte && $quantiteSignee > 0) {
            $quantiteSignee = -$quantiteSignee;
        }
        self::exigerMotif($motif);

        return DB::transaction(fn () => self::ecrire(
            self::verrouiller($intrant), $magasin, $type, $quantiteSignee, $date, $auteur, motif: $motif,
        ));
    }

    public static function distribuer(Pret $pret, Intrant $intrant, Magasin $magasin, int $quantite, Carbon $date, User $auteur): MouvementIntrant
    {
        self::verifierDroit($auteur, 'decaisser-prets');

        return DB::transaction(function () use ($pret, $intrant, $magasin, $quantite, $date, $auteur) {
            $pret = Pret::query()->lockForUpdate()->findOrFail($pret->id);
            $intrant = self::verrouiller($intrant);

            if ($pret->statut !== StatutPret::Valide) {
                throw new OperationRefusee('Seul un prêt validé et pas encore entièrement remis reçoit des intrants (statut : '.$pret->statut->libelle().').');
            }
            if (! $pret->forme->accepteIntrants()) {
                throw new OperationRefusee('Ce prêt est en '.$pret->forme->libelle().' : il ne se remet pas en intrants.');
            }
            if ($quantite <= 0 || $quantite > self::QUANTITE_MAX) {
                throw new OperationRefusee('La quantité doit être un nombre entier supérieur à zéro.');
            }

            // Valeur figée au prix du jour, en entiers (D4).
            $valeur = $quantite * $intrant->prix_unitaire_fcfa;
            $reste = $pret->resteARemettre();
            if ($valeur > $reste) {
                throw new OperationRefusee('Ces intrants valent '.Format::fcfa($valeur).', plus que le reste à remettre sur ce prêt ('.Format::fcfa($reste).').');
            }

            $mouvement = self::ecrire(
                $intrant, $magasin, TypeMouvementIntrant::Distribution, -$quantite, $date, $auteur,
                pret: $pret, valeur: -$valeur, prixUnitaire: $intrant->prix_unitaire_fcfa,
            );

            Prets::marquerSiToutRemis($pret);

            return $mouvement;
        });
    }

    /**
     * Annule un mouvement par son inverse (quantité et valeur). Une distribution annulée
     * revient en stock et ne compte plus dans ce que le producteur a reçu.
     */
    public static function contrePasser(MouvementIntrant $mouvement, string $motif, User $auteur): MouvementIntrant
    {
        self::verifierDroit($auteur, 'gerer-intrants');
        self::exigerMotif($motif);

        return DB::transaction(function () use ($mouvement, $motif, $auteur) {
            $intrant = self::verrouiller($mouvement->intrant);
            $original = MouvementIntrant::query()->findOrFail($mouvement->id);

            if ($original->type === TypeMouvementIntrant::ContrePassation) {
                throw new OperationRefusee('Une contre-passation ne se contre-passe pas : saisir à nouveau le mouvement correct.');
            }
            if (MouvementIntrant::query()->where('annule_id', $original->id)->exists()) {
                throw new OperationRefusee('Ce mouvement a déjà été contre-passé.');
            }

            $inverse = self::ecrire(
                $intrant, $original->magasin, TypeMouvementIntrant::ContrePassation, -$original->quantite, Carbon::today(), $auteur,
                pret: $original->pret, valeur: $original->valeur_fcfa === null ? null : -$original->valeur_fcfa,
                prixUnitaire: $original->prix_unitaire_fcfa, motif: trim($motif), annule: $original,
            );

            // Une remise annulée rouvre le reste à remettre du prêt.
            if ($original->pret !== null && $original->pret->statut === StatutPret::Decaisse) {
                $original->pret->update(['statut' => StatutPret::Valide]);
            }

            return $inverse;
        });
    }

    private static function verrouiller(Intrant $intrant): Intrant
    {
        return Intrant::query()->lockForUpdate()->findOrFail($intrant->id);
    }

    private static function ecrire(
        Intrant $intrant,
        Magasin $magasin,
        TypeMouvementIntrant $type,
        int $quantite,
        Carbon $date,
        User $auteur,
        ?Pret $pret = null,
        ?int $valeur = null,
        ?int $prixUnitaire = null,
        ?string $motif = null,
        ?MouvementIntrant $annule = null,
    ): MouvementIntrant {
        if ($quantite === 0 || abs($quantite) > self::QUANTITE_MAX) {
            throw new OperationRefusee('La quantité doit être un nombre entier différent de zéro.');
        }
        if ($type === TypeMouvementIntrant::Entree && $quantite < 0) {
            throw new OperationRefusee('Une entrée en stock est une quantité positive.');
        }
        if ($annule === null && (! $intrant->actif || ! $magasin->actif)) {
            throw new OperationRefusee('L\'intrant ou le magasin est désactivé.');
        }
        if ($date->isAfter(Carbon::today())) {
            throw new OperationRefusee('La date ne peut pas être dans le futur.');
        }

        if ($quantite < 0) {
            $stock = $intrant->stock($magasin->id);
            if ($stock + $quantite < 0) {
                throw new OperationRefusee("Stock insuffisant de « {$intrant->nom} » au magasin « {$magasin->nom} » : "
                    .$stock.' '.$intrant->unite->libelle($stock).' disponible(s), '.(-$quantite).' demandé(s).');
            }
        }

        return MouvementIntrant::query()->create([
            'intrant_id' => $intrant->id,
            'magasin_id' => $magasin->id,
            'type' => $type,
            'quantite' => $quantite,
            'valeur_fcfa' => $valeur,
            'prix_unitaire_fcfa' => $prixUnitaire,
            'pret_id' => $pret?->id,
            'date_mouvement' => $date->toDateString(),
            'motif' => $motif,
            'annule_id' => $annule?->id,
            'cree_par' => $auteur->id,
        ]);
    }

    private static function exigerMotif(string $motif): void
    {
        if (mb_strlen(trim($motif)) < 5) {
            throw new OperationRefusee('Le motif est obligatoire (5 caractères au moins).');
        }
    }

    private static function verifierDroit(User $auteur, string $droit): void
    {
        if (! $auteur->can($droit)) {
            throw new OperationRefusee('Votre rôle ne permet pas cette opération sur les intrants.');
        }
    }
}
