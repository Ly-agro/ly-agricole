<?php

namespace App\Http\Controllers;

use App\Models\Producteur;
use App\Services\CarteProducteur;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProducteurController extends Controller
{
    public function fiche(Producteur $producteur): View
    {
        Gate::authorize('voir-producteurs');

        $producteur->load(['village.zone', 'groupe', 'auteurConsentement', 'parcelles' => fn ($q) => $q->with('produit')
            ->withCount('visites')->withMax('visites', 'date_visite')->orderBy('nom')]);

        return view('producteurs.fiche', ['producteur' => $producteur]);
    }

    public function carte(Producteur $producteur): Response
    {
        Gate::authorize('gerer-producteurs');

        return CarteProducteur::telecharger($producteur);
    }

    /**
     * La photo vit sur le disque privé : elle ne passe que par ici, droit vérifié.
     */
    public function photo(Producteur $producteur): StreamedResponse
    {
        Gate::authorize('voir-producteurs');
        abort_if($producteur->photo === null || ! Storage::disk('local')->exists($producteur->photo), 404);

        return Storage::disk('local')->response($producteur->photo, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
