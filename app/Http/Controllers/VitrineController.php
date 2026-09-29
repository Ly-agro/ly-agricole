<?php

namespace App\Http\Controllers;

use App\Models\Actualite;
use App\Services\Publications;
use Illuminate\Contracts\View\View;

/** Vitrine publique : prix bord-champ affichés et dernières actualités. Aucune donnée de gestion. */
class VitrineController extends Controller
{
    public function accueil(): View
    {
        return view('vitrine', [
            'prix' => Publications::prixCourants(),
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
