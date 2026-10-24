<?php

namespace App\Services;

use App\Enums\PosteBudget;
use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Enums\StatutDepense;
use App\Exceptions\OperationRefusee;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\Decaissement;
use App\Models\Depense;
use App\Models\LigneBudget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Budget de campagne, prévu contre réel (cahier §8). Le prévu est saisi par poste ; le
 * réel n'est jamais stocké, il se recalcule à chaque lecture depuis les registres :
 *
 * - dépenses : payées, rattachées à la campagne, par catégorie (une dépense annulée —
 *   paiement contre-passé — ne compte plus) ;
 * - achats : argent payé en espèces sur les achats validés de la campagne ; la part
 *   retenue sur un prêt n'est pas de l'argent sorti (il l'est déjà au décaissement) ;
 * - prêts : argent décaissé, hors décaissements contre-passés. Les intrants remis à
 *   crédit n'y sont pas : leur achat est déjà une dépense.
 *
 * « En attente » = ce qui sortira si on valide ce qui attend (dépenses et achats à
 * valider) : ni dépensé, ni à ignorer.
 */
class Budgets
{
    /**
     * Fixe (crée ou modifie) le montant prévu d'un poste. Le motif, facultatif, ne sert
     * qu'à une modification : il entre au journal avec l'ancien et le nouveau montant.
     */
    public static function definir(Campagne $campagne, PosteBudget $poste, ?int $categorieId, int $montant, User $auteur, ?string $note = null, ?string $motif = null): LigneBudget
    {
        if (! $auteur->can('gerer-budget')) {
            throw new OperationRefusee('Votre rôle ne permet pas de modifier le budget.');
        }
        if ($montant < 0) {
            throw new OperationRefusee('Le montant prévu ne peut pas être négatif.');
        }

        return DB::transaction(function () use ($campagne, $poste, $categorieId, $montant, $auteur, $note, $motif) {
            // Verrou sur la campagne : deux saisies simultanées du même poste ne créent
            // pas deux lignes (l'index unique ne compare pas les categorie_id NULL).
            $campagne = Campagne::query()->lockForUpdate()->findOrFail($campagne->id);

            if ($campagne->statut === StatutCampagne::Cloturee) {
                throw new OperationRefusee("La campagne {$campagne->code} est clôturée : son budget n'est plus modifiable.");
            }

            if ($poste === PosteBudget::Categorie) {
                if ($categorieId === null) {
                    throw new OperationRefusee('Choisissez la catégorie de dépense.');
                }
                $categorie = CategorieDepense::query()->findOrFail($categorieId);
                if ($categorie->exclue_fonds_campagne) {
                    throw new OperationRefusee("La catégorie « {$categorie->nom} » est exclue des fonds de campagne (contrat, art. 10.3) : elle ne se budgète pas sur la campagne.");
                }
            } else {
                $categorieId = null;
            }

            $note = filled($note) ? trim((string) $note) : null;
            $ligne = LigneBudget::query()->where('campagne_id', $campagne->id)->where('poste', $poste->value)
                ->where('categorie_id', $categorieId)->first();

            if ($ligne === null) {
                return LigneBudget::query()->create([
                    'campagne_id' => $campagne->id,
                    'poste' => $poste,
                    'categorie_id' => $categorieId,
                    'montant_fcfa' => $montant,
                    'note' => $note,
                    'cree_par' => $auteur->id,
                ]);
            }

            $ligne->update([
                'montant_fcfa' => $montant,
                'note' => $note,
                'modifie_par' => $auteur->id,
                'motif_modification' => filled($motif) ? trim((string) $motif) : null,
            ]);

            return $ligne;
        });
    }

    /**
     * Prévu contre réel d'une campagne. Une ligne par poste budgété, plus une par
     * catégorie dépensée sans budget (prévu null : c'est justement ce qu'il faut voir).
     *
     * @return array{lignes: Collection<int, array{cle: string, libelle: string, ligne: LigneBudget|null, prevu: int|null, reel: int, en_attente: int, ecart: int|null, consomme_pour_mille: int|null}>, prevu: int, reel: int, en_attente: int}
     */
    public static function suivi(Campagne $campagne): array
    {
        $budget = LigneBudget::query()->where('campagne_id', $campagne->id)->with('categorie')->get();

        $depenses = fn (StatutDepense $statut) => Depense::query()->where('campagne_id', $campagne->id)->where('statut', $statut)
            ->groupBy('categorie_id')->selectRaw('categorie_id, SUM(montant_fcfa) AS total')
            ->pluck('total', 'categorie_id')->map(fn ($t) => (int) $t);
        $payees = $depenses(StatutDepense::Payee);
        $aValider = $depenses(StatutDepense::AValider);

        $achats = fn (StatutAchat $statut) => (int) Achat::query()->where('campagne_id', $campagne->id)
            ->where('statut', $statut)->sum('montant_especes_fcfa');

        $reel = [
            'achats' => $achats(StatutAchat::Valide),
            'prets' => (int) Decaissement::query()
                ->whereHas('pret', fn ($q) => $q->where('campagne_id', $campagne->id))
                ->whereDoesntHave('mouvement.contrePassation')
                ->sum('montant_fcfa'),
        ];
        $enAttente = ['achats' => $achats(StatutAchat::AValider), 'prets' => 0];

        $lignes = collect();
        foreach ([PosteBudget::Achats, PosteBudget::Prets] as $poste) {
            $ligne = $budget->first(fn (LigneBudget $l) => $l->poste === $poste);
            $lignes->push(self::ligne($poste->value, $poste->libelle(), $ligne, $reel[$poste->value], $enAttente[$poste->value]));
        }

        $categoriesBudgetees = $budget->where('poste', PosteBudget::Categorie)->keyBy('categorie_id');
        $idsCategories = $categoriesBudgetees->keys()->merge($payees->keys())->merge($aValider->keys())->unique();
        $noms = CategorieDepense::query()->whereKey($idsCategories)->pluck('nom', 'id');

        $lignes = $lignes->merge($idsCategories
            ->map(fn ($id) => self::ligne(
                "categorie-$id", (string) ($noms[$id] ?? "Catégorie #$id"), $categoriesBudgetees->get($id),
                $payees->get($id, 0), $aValider->get($id, 0),
            ))
            ->sortBy('libelle', SORT_NATURAL | SORT_FLAG_CASE));

        return [
            'lignes' => $lignes->values(),
            'prevu' => (int) $lignes->sum(fn (array $l) => $l['prevu'] ?? 0),
            'reel' => (int) $lignes->sum('reel'),
            'en_attente' => (int) $lignes->sum('en_attente'),
        ];
    }

    /** @return array{cle: string, libelle: string, ligne: LigneBudget|null, prevu: int|null, reel: int, en_attente: int, ecart: int|null, consomme_pour_mille: int|null} */
    private static function ligne(string $cle, string $libelle, ?LigneBudget $ligne, int $reel, int $enAttente): array
    {
        $prevu = $ligne?->montant_fcfa;

        return [
            'cle' => $cle,
            'libelle' => $libelle,
            'ligne' => $ligne,
            'prevu' => $prevu,
            'reel' => $reel,
            'en_attente' => $enAttente,
            // Positif = reste disponible ; négatif = dépassement.
            'ecart' => $prevu === null ? null : $prevu - $reel,
            'consomme_pour_mille' => $prevu === null || $prevu === 0 ? null : intdiv($reel * 1000, $prevu),
        ];
    }
}
