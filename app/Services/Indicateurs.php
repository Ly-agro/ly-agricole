<?php

namespace App\Services;

use App\Enums\NatureMouvement;
use App\Enums\SensMouvement;
use App\Enums\StatutDepense;
use App\Enums\StatutPret;
use App\Models\Campagne;
use App\Models\Decaissement;
use App\Models\Depense;
use App\Models\MouvementTresorerie;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres du tableau de bord. Lecture seule : tout est recalculé depuis les registres
 * (jamais de total stocké), en FCFA entiers.
 *
 * Ce qui n'existe pas encore (achats, stock, reventes, remboursements) n'est pas
 * inventé ici : les écrans le disent, et les chiffres arrivent avec leurs modules.
 */
class Indicateurs
{
    /** Prêts qui engagent de l'argent : validés ou déjà versés. */
    private const STATUTS_ACCORDES = [StatutPret::Valide->value, StatutPret::Decaisse->value];

    /** @return Collection<int, Campagne> Les plus récentes d'abord. */
    public function campagnes(): Collection
    {
        return Campagne::query()->with('produit')->orderByDesc('debut')->get();
    }

    /**
     * La campagne à montrer par défaut : la première ouverte, sinon la plus récente.
     *
     * @param  Collection<int, Campagne>  $campagnes
     */
    public function campagneParDefaut(Collection $campagnes): ?Campagne
    {
        return $campagnes->first(fn (Campagne $c) => $c->statut->value === 'ouverte') ?? $campagnes->first();
    }

    /**
     * @return array{accordes: int, nombre: int, remis: int, depenses: int, producteurs: int}
     */
    public function chiffresCampagne(Campagne $campagne): array
    {
        $prets = Pret::query()->where('campagne_id', $campagne->id)->whereIn('statut', self::STATUTS_ACCORDES);

        return [
            'accordes' => (int) (clone $prets)->sum('montant_fcfa'),
            'nombre' => (clone $prets)->count(),
            'remis' => $this->remisParCampagne()[$campagne->id] ?? 0,
            'depenses' => (int) Depense::query()->where('campagne_id', $campagne->id)
                ->where('statut', StatutDepense::Payee)->sum('montant_fcfa'),
            'producteurs' => (clone $prets)->distinct()->count('producteur_id'),
        ];
    }

    /**
     * Argent versé (hors paiements contre-passés) + valeur des intrants remis, par campagne.
     *
     * @return array<int, int>
     */
    private function remisParCampagne(): array
    {
        $argent = Decaissement::query()
            ->join('prets', 'prets.id', '=', 'decaissements.pret_id')
            ->whereDoesntHave('mouvement.contrePassation')
            ->groupBy('prets.campagne_id')
            ->selectRaw('prets.campagne_id AS campagne_id, SUM(decaissements.montant_fcfa) AS total')
            ->pluck('total', 'campagne_id');

        // Distributions en négatif, contre-passations en positif : la somme se compense.
        $intrants = DB::table('mouvements_intrants')
            ->join('prets', 'prets.id', '=', 'mouvements_intrants.pret_id')
            ->groupBy('prets.campagne_id')
            ->selectRaw('prets.campagne_id AS campagne_id, -SUM(mouvements_intrants.valeur_fcfa) AS total')
            ->pluck('total', 'campagne_id');

        $resultat = [];
        foreach ($argent->keys()->merge($intrants->keys())->unique() as $id) {
            $resultat[(int) $id] = (int) ($argent[$id] ?? 0) + (int) ($intrants[$id] ?? 0);
        }

        return $resultat;
    }

    /**
     * Une ligne par campagne, pour « ce qui a été fait ».
     *
     * @param  Collection<int, Campagne>  $campagnes
     * @return Collection<int, array{campagne: Campagne, prets: int, accordes: int, remis: int, depenses: int, producteurs: int}>
     */
    public function bilanParCampagne(Collection $campagnes): Collection
    {
        $remis = $this->remisParCampagne();
        $prets = DB::table('prets')->whereIn('statut', self::STATUTS_ACCORDES)->groupBy('campagne_id')
            ->selectRaw('campagne_id, COUNT(*) AS n, SUM(montant_fcfa) AS total, COUNT(DISTINCT producteur_id) AS producteurs')
            ->get()->keyBy('campagne_id');
        $depenses = DB::table('depenses')->where('statut', StatutDepense::Payee->value)->whereNotNull('campagne_id')
            ->groupBy('campagne_id')->selectRaw('campagne_id, SUM(montant_fcfa) AS total')->pluck('total', 'campagne_id');

        return $campagnes->map(fn (Campagne $c) => [
            'campagne' => $c,
            'prets' => (int) ($prets[$c->id]->n ?? 0),
            'accordes' => (int) ($prets[$c->id]->total ?? 0),
            'remis' => $remis[$c->id] ?? 0,
            'depenses' => (int) ($depenses[$c->id] ?? 0),
            'producteurs' => (int) ($prets[$c->id]->producteurs ?? 0),
        ]);
    }

    /** Somme des soldes des comptes actifs (Σ entrées − Σ sorties). */
    public function tresorerieTotale(): int
    {
        return (int) MouvementTresorerie::query()
            ->join('comptes_tresorerie', 'comptes_tresorerie.id', '=', 'mouvements_tresorerie.compte_id')
            ->where('comptes_tresorerie.actif', true)
            ->selectRaw('COALESCE(SUM(CASE WHEN mouvements_tresorerie.sens = ? THEN mouvements_tresorerie.montant_fcfa ELSE -mouvements_tresorerie.montant_fcfa END), 0) AS solde', [SensMouvement::Entree->value])
            ->value('solde');
    }

    /**
     * Entrées et sorties d'argent par mois, sur les `$mois` derniers mois. Les virements
     * internes sont exclus : ils ne font qu'écarter l'argent d'un compte à l'autre.
     *
     * @return list<array{libelle: string, entrees: int, sorties: int}>
     */
    public function fluxMensuels(int $mois = 6): array
    {
        $debut = Carbon::today()->startOfMonth()->subMonths($mois - 1);

        $lignes = MouvementTresorerie::query()
            ->where('date_operation', '>=', $debut)
            ->where('nature', '!=', NatureMouvement::Virement)
            ->get(['sens', 'montant_fcfa', 'date_operation']);

        $serie = [];
        for ($i = 0; $i < $mois; $i++) {
            $mois_i = $debut->copy()->addMonths($i);
            $serie[$mois_i->format('Y-m')] = [
                'libelle' => ucfirst($mois_i->locale('fr')->isoFormat('MMM')),
                'entrees' => 0,
                'sorties' => 0,
            ];
        }

        foreach ($lignes as $ligne) {
            $cle = $ligne->date_operation->format('Y-m');
            if (isset($serie[$cle])) {
                $serie[$cle][$ligne->sens === SensMouvement::Entree ? 'entrees' : 'sorties'] += $ligne->montant_fcfa;
            }
        }

        return array_values($serie);
    }

    /**
     * Entrées d'argent du même intervalle, par nature (apports, autres entrées…).
     * Ce n'est pas encore un chiffre d'affaires : les reventes viennent en phase 2.
     *
     * @return Collection<int, array{label: string, valeur: int}>
     */
    public function entreesParNature(int $mois = 6): Collection
    {
        $debut = Carbon::today()->startOfMonth()->subMonths($mois - 1);

        return DB::table('mouvements_tresorerie')
            ->where('date_operation', '>=', $debut->toDateString())
            ->where('sens', SensMouvement::Entree->value)
            ->whereNotIn('nature', [NatureMouvement::Virement->value, NatureMouvement::ContrePassation->value])
            ->groupBy('nature')->selectRaw('nature, SUM(montant_fcfa) AS total')->get()
            ->map(fn ($l) => [
                'label' => NatureMouvement::from((string) $l->nature)->libelle(),
                'valeur' => (int) $l->total,
            ])->sortByDesc('valeur')->values();
    }

    /** @return Collection<int, array{label: string, valeur: int}> */
    public function depensesParCategorie(Campagne $campagne): Collection
    {
        return DB::table('depenses')
            ->join('categories_depense', 'categories_depense.id', '=', 'depenses.categorie_id')
            ->where('depenses.campagne_id', $campagne->id)
            ->where('depenses.statut', StatutDepense::Payee->value)
            ->groupBy('categories_depense.nom')
            ->selectRaw('categories_depense.nom AS nom, SUM(depenses.montant_fcfa) AS total')
            ->orderByDesc('total')->get()
            ->map(fn ($l) => ['label' => (string) $l->nom, 'valeur' => (int) $l->total]);
    }

    /** @return Collection<int, array{label: string, valeur: int}> Nombre de prêts par statut. */
    public function pretsParStatut(Campagne $campagne): Collection
    {
        $comptes = DB::table('prets')->where('campagne_id', $campagne->id)
            ->groupBy('statut')->selectRaw('statut, COUNT(*) AS n')->get()
            ->mapWithKeys(fn ($l) => [(string) $l->statut => (int) $l->n]);

        return collect(StatutPret::cases())
            ->map(fn (StatutPret $s) => ['label' => $s->libelle(), 'valeur' => $comptes[$s->value] ?? 0]);
    }

    /**
     * Ce qui attend une action, limité à ce que le rôle a le droit de faire.
     *
     * @return list<array{texte: string, nombre: int, lien: string}>
     */
    public function aFaire(User $user): array
    {
        $taches = [];

        if ($user->can('valider-prets')) {
            $n = Pret::query()->where('statut', StatutPret::Demande)->count();
            $n > 0 && $taches[] = ['texte' => $n > 1 ? 'demandes de prêt à valider' : 'demande de prêt à valider', 'nombre' => $n, 'lien' => route('prets', ['statut' => StatutPret::Demande->value])];
        }

        if ($user->can('decaisser-prets')) {
            $n = Pret::query()->where('statut', StatutPret::Valide)->count();
            $n > 0 && $taches[] = ['texte' => $n > 1 ? 'prêts validés à décaisser' : 'prêt validé à décaisser', 'nombre' => $n, 'lien' => route('prets', ['statut' => StatutPret::Valide->value])];
        }

        if ($user->can('valider-depenses')) {
            $n = Depense::query()->where('statut', StatutDepense::AValider)->where('cree_par', '!=', $user->id)->count();
            $n > 0 && $taches[] = ['texte' => $n > 1 ? 'dépenses à valider' : 'dépense à valider', 'nombre' => $n, 'lien' => route('depenses')];
        }

        if ($user->can('voir-prets')) {
            $n = Pret::query()->whereIn('statut', self::STATUTS_ACCORDES)->where('echeance', '<', Carbon::today())->count();
            $n > 0 && $taches[] = ['texte' => $n > 1 ? 'prêts dont l\'échéance est passée' : 'prêt dont l\'échéance est passée', 'nombre' => $n, 'lien' => route('prets')];
        }

        return $taches;
    }

    /**
     * Fil d'actualité : les derniers faits enregistrés, fusionnés et triés, filtrés par
     * droit (un agent ne voit pas les dépenses, par exemple).
     *
     * @return Collection<int, array{quand: Carbon, texte: string, detail: string, lien: string|null, genre: string}>
     */
    public function actualite(User $user, int $limite = 8): Collection
    {
        $faits = collect();

        if ($user->can('voir-prets')) {
            Pret::query()->with('producteur')->latest()->limit($limite)->get()->each(function (Pret $p) use ($faits) {
                $faits->push([
                    'quand' => $p->created_at,
                    'texte' => 'Demande de prêt '.$p->reference,
                    'detail' => $p->producteur->nom.' '.$p->producteur->prenoms.' · '.Format::fcfa($p->montant_fcfa),
                    'lien' => route('prets.fiche', $p),
                    'genre' => 'pret',
                ]);
            });

            Decaissement::query()->with('pret.producteur')->latest('created_at')->limit($limite)->get()->each(function (Decaissement $d) use ($faits) {
                $faits->push([
                    'quand' => $d->created_at,
                    'texte' => 'Versement sur '.$d->pret->reference,
                    'detail' => $d->pret->producteur->nom.' · '.Format::fcfa($d->montant_fcfa),
                    'lien' => route('prets.fiche', $d->pret),
                    'genre' => 'versement',
                ]);
            });
        }

        if ($user->can('valider-depenses')) {
            Depense::query()->latest()->limit($limite)->get()->each(function (Depense $d) use ($faits) {
                $faits->push([
                    'quand' => $d->created_at,
                    'texte' => 'Dépense · '.$d->beneficiaire,
                    'detail' => Format::fcfa($d->montant_fcfa).' · '.$d->statut->libelle(),
                    'lien' => route('depenses'),
                    'genre' => 'depense',
                ]);
            });
        }

        if ($user->can('voir-producteurs')) {
            Producteur::query()->latest()->limit($limite)->get()->each(function (Producteur $p) use ($faits) {
                $faits->push([
                    'quand' => $p->created_at,
                    'texte' => 'Nouveau producteur',
                    'detail' => $p->nom.' '.$p->prenoms,
                    'lien' => route('producteurs.fiche', $p),
                    'genre' => 'producteur',
                ]);
            });
        }

        return $faits->sortByDesc('quand')->take($limite)->values();
    }
}
