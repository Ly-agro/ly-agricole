<?php

namespace App\Http\Controllers;

use App\Models\Actualite;
use App\Models\Produit;
use App\Services\Publications;
use Illuminate\Contracts\View\View;

/** Vitrine publique : prix bord-champ affichés et dernières actualités. Aucune donnée de gestion. */
class VitrineController extends Controller
{
    public function accueil(): View
    {
        $prix = Publications::prixCourants();
        $avecPrix = $prix->map(fn (array $ligne) => $ligne['produit']->id)->all();

        return view('vitrine', [
            'prix' => $prix,
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
