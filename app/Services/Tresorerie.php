<?php

namespace App\Services;

use App\Enums\NatureMouvement;
use App\Enums\SensMouvement;
use App\Enums\StatutDepense;
use App\Exceptions\OperationRefusee;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\MouvementTresorerie;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seule porte d'entrée des mouvements de trésorerie (registre immuable 🔒).
 *
 * Chaque opération : une transaction ; les comptes touchés sont verrouillés (dans
 * l'ordre des id, pour éviter les interblocages) ; aucun compte ne passe en négatif
 * (invariant 3) ; une erreur se corrige par contre-passation, jamais par modification.
 */
class Tresorerie
{
    public const MONTANT_MAX = 999_999_999_999;

    public static function entree(
        CompteTresorerie $compte,
        int $montant,
        NatureMouvement $nature,
        Carbon $date,
        string $libelle,
        User $auteur,
        ?string $reference = null,
    ): MouvementTresorerie {
        if (! in_array($nature, NatureMouvement::entreesManuelles(), true)) {
            throw new OperationRefusee('Cette nature de mouvement ne se saisit pas comme une simple entrée.');
        }

        return DB::transaction(function () use ($compte, $montant, $nature, $date, $libelle, $auteur, $reference) {
            $comptes = self::verrouiller([$compte->id]);

            return self::ecrire($comptes[$compte->id], SensMouvement::Entree, $montant, $nature, $date, $libelle, $auteur, reference: $reference);
        });
    }

    /**
     * Virement entre deux comptes : deux mouvements liés, tout ou rien.
     *
     * @return array{sortie: MouvementTresorerie, entree: MouvementTresorerie}
     */
    public static function virement(
        CompteTresorerie $depuis,
        CompteTresorerie $vers,
        int $montant,
        Carbon $date,
        string $libelle,
        User $auteur,
        NatureMouvement $nature = NatureMouvement::Virement,
        ?string $reference = null,
    ): array {
        if ($depuis->is($vers)) {
            throw new OperationRefusee('Un virement se fait entre deux comptes différents.');
        }

        return DB::transaction(function () use ($depuis, $vers, $montant, $date, $libelle, $auteur, $nature, $reference) {
            $comptes = self::verrouiller([$depuis->id, $vers->id]);
            $lien = (string) Str::uuid7();

            return [
                'sortie' => self::ecrire($comptes[$depuis->id], SensMouvement::Sortie, $montant, $nature, $date, $libelle, $auteur, lien: $lien, reference: $reference),
                'entree' => self::ecrire($comptes[$vers->id], SensMouvement::Entree, $montant, $nature, $date, $libelle, $auteur, lien: $lien, reference: $reference),
            ];
        });
    }

    /**
     * Avance à un agent = virement vers SA caisse. Le solde de cette caisse est ce qu'il
     * lui reste à justifier (achats et dépenses payés depuis elle).
     *
     * @return array{sortie: MouvementTresorerie, entree: MouvementTresorerie}
     */
    public static function avanceAgent(CompteTresorerie $depuis, CompteTresorerie $caisseAgent, int $montant, Carbon $date, string $libelle, User $auteur): array
    {
        if ($caisseAgent->titulaire_id === null) {
            throw new OperationRefusee('Une avance se verse sur la caisse d\'un agent (compte avec titulaire).');
        }

        return self::virement($depuis, $caisseAgent, $montant, $date, $libelle, $auteur, NatureMouvement::AvanceAgent);
    }

    /** Paiement d'une dépense : appelé par App\Services\Depenses, dans sa transaction. */
    public static function payerDepense(Depense $depense, User $auteur): MouvementTresorerie
    {
        return DB::transaction(function () use ($depense, $auteur) {
            $comptes = self::verrouiller([$depense->compte_id]);

            return self::ecrire(
                $comptes[$depense->compte_id], SensMouvement::Sortie, $depense->montant_fcfa, NatureMouvement::Depense,
                $depense->date_depense, 'Dépense : '.$depense->beneficiaire, $auteur, source: $depense,
            );
        });
    }

    /**
     * Annule un mouvement par un mouvement inverse (motif obligatoire). Un virement est
     * annulé sur ses deux jambes ; une dépense payée passe « annulée ».
     *
     * @return list<MouvementTresorerie> les contre-passations créées
     */
    public static function contrePasser(MouvementTresorerie $mouvement, string $motif, User $auteur, ?Carbon $date = null): array
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            throw new OperationRefusee('Le motif de la contre-passation est obligatoire (5 caractères au moins).');
        }

        return DB::transaction(function () use ($mouvement, $motif, $auteur, $date) {
            $originaux = $mouvement->lien === null
                ? MouvementTresorerie::query()->whereKey($mouvement->id)->get()
                : MouvementTresorerie::query()->where('lien', $mouvement->lien)->orderBy('id')->get();

            $comptes = self::verrouiller($originaux->pluck('compte_id')->all());

            // Relecture sous verrou : deux personnes ne contre-passent pas le même mouvement.
            foreach ($originaux as $original) {
                if ($original->nature === NatureMouvement::ContrePassation) {
                    throw new OperationRefusee('Une contre-passation ne se contre-passe pas : saisir à nouveau l\'opération correcte.');
                }
                if (MouvementTresorerie::query()->where('annule_id', $original->id)->exists()) {
                    throw new OperationRefusee('Ce mouvement a déjà été contre-passé.');
                }
            }

            $date ??= Carbon::today();
            $crees = [];
            // Les entrées d'abord : annuler un virement retire l'argent de la destination
            // avant de le rendre à la source.
            foreach ($originaux->sortBy(fn (MouvementTresorerie $m) => $m->sens === SensMouvement::Entree ? 0 : 1) as $original) {
                $crees[] = self::ecrire(
                    $comptes[$original->compte_id], $original->sens->inverse(), $original->montant_fcfa,
                    NatureMouvement::ContrePassation, $date, 'Contre-passation : '.$original->libelle, $auteur,
                    lien: $original->lien, annule: $original, motif: $motif,
                );
            }

            $depenseIds = $originaux->where('source_type', 'depense')->pluck('source_id')->filter();
            Depense::query()->whereIn('id', $depenseIds)->get()->each->update(['statut' => StatutDepense::Annulee]);

            return $crees;
        });
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, CompteTresorerie> indexée par id
     */
    private static function verrouiller(array $ids): Collection
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return CompteTresorerie::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private static function ecrire(
        CompteTresorerie $compte,
        SensMouvement $sens,
        int $montant,
        NatureMouvement $nature,
        Carbon $date,
        string $libelle,
        User $auteur,
        ?string $lien = null,
        ?string $reference = null,
        ?Model $source = null,
        ?MouvementTresorerie $annule = null,
        ?string $motif = null,
    ): MouvementTresorerie {
        if ($montant <= 0 || $montant > self::MONTANT_MAX) {
            throw new OperationRefusee('Le montant doit être un nombre entier de FCFA supérieur à zéro.');
        }
        if (! $compte->actif && $annule === null) {
            throw new OperationRefusee("Le compte « {$compte->nom} » est désactivé.");
        }
        if ($date->isAfter(Carbon::today())) {
            throw new OperationRefusee('La date d\'une opération ne peut pas être dans le futur.');
        }
        if (trim($libelle) === '') {
            throw new OperationRefusee('Le libellé est obligatoire.');
        }

        if ($sens === SensMouvement::Sortie) {
            $solde = $compte->solde();
            if ($solde - $montant < 0) {
                throw new OperationRefusee("Solde insuffisant sur « {$compte->nom} » : ".Format::fcfa($solde)
                    .' disponibles, '.Format::fcfa($montant).' demandés.');
            }
        }

        return MouvementTresorerie::query()->create([
            'compte_id' => $compte->id,
            'sens' => $sens,
            'montant_fcfa' => $montant,
            'nature' => $nature,
            'date_operation' => $date->toDateString(),
            'libelle' => Str::limit(trim($libelle), 250, ''),
            'reference_externe' => $reference ?: null,
            'lien' => $lien,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source ? (string) $source->getKey() : null,
            'annule_id' => $annule?->id,
            'motif' => $motif,
            'cree_par' => $auteur->id,
        ]);
    }
}
