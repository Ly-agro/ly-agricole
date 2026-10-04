<?php

namespace App\Http\Controllers;

use App\Models\Actualite;
use App\Models\Produit;
use App\Services\Publications;
use Illuminate\Contracts\View\View;

/** Vitrine publique : prix bord-champ affichés et dernières actualités. Aucune donnée de gestion. */
class VitrineController extends Controller
{
    /** Cartes affichées d'emblée ; les autres prix sont derrière « Afficher les autres ». */
    public const PRIX_EN_AVANT = 6;

    /** Les filières de LY d'abord, puis les grandes cultures d'export ; le reste par ordre alphabétique. */
    private const ORDRE_VEDETTES = ['anacarde', 'karite', 'tomate', 'cacao', 'cafe', 'hevea'];

    public function accueil(): View
    {
        $prix = Publications::prixCourants()->sortBy(function (array $ligne) {
            $rang = array_search($ligne['produit']->code, self::ORDRE_VEDETTES, true);

            return [$rang === false ? count(self::ORDRE_VEDETTES) : $rang, mb_strtolower($ligne['produit']->nom)];
        })->values();
        $avecPrix = $prix->map(fn (array $ligne) => $ligne['produit']->id)->all();

        return view('vitrine', [
            'prix' => $prix,
            'prixEnAvant' => $prix->take(self::PRIX_EN_AVANT),
            // Filière phare : son dernier prix publié s'affiche dans la section « de la noix à l'amande ».
            'prixAnacarde' => $prix->first(fn (array $ligne) => $ligne['produit']->code === 'anacarde'),
            'prixAutres' => $prix->slice(self::PRIX_EN_AVANT)->values(),
            // Cultures suivies mais sans prix publié : affichées, avec « prix à venir », jamais un prix inventé.
            'cultures' => Produit::query()->where('actif', true)->orderBy('nom')->get(),
            'sansPrix' => Produit::query()->where('actif', true)->whereNotIn('id', $avecPrix)->orderBy('nom')->get(),
            'actualites' => Publications::actualitesVisibles(3),
        ]);
    }

    public function actualites(): View
    {
        return view('actualites.index', ['actualites' => Publications::actualitesVisibles()]);
    }

    /** Un brouillon, ou une actualité datée du futur, n'existe pas pour le public. */
    public function actualite(Actualite $actualite): View
    {
        abort_unless(Actualite::query()->visibles()->whereKey($actualite->id)->exists(), 404);

        return view('actualites.show', ['actualite' => $actualite]);
    }
}
