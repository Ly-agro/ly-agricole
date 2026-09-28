<?php

use App\Http\Controllers\Auth\DeconnexionController;
use App\Livewire\Auth\Connexion;
use App\Livewire\Journal\ConsultationJournal;
use App\Livewire\Referentiels\Campagnes;
use App\Livewire\Referentiels\EcranReferentiel;
use App\Livewire\Referentiels\Magasins;
use App\Livewire\Referentiels\Parametres;
use App\Livewire\Referentiels\PointsCollecte;
use App\Livewire\Referentiels\Produits;
use App\Livewire\Referentiels\Villages;
use App\Livewire\Referentiels\Zones;
use App\Livewire\Utilisateurs\GestionUtilisateurs;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tableau-de-bord');

// Nommée `login` : c'est la route où Laravel renvoie un visiteur non connecté.
Route::get('/connexion', Connexion::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::view('/tableau-de-bord', 'tableau-de-bord')->name('tableau-de-bord');

    Route::get('/utilisateurs', GestionUtilisateurs::class)
        ->middleware('can:gerer-utilisateurs')
        ->name('utilisateurs');

    Route::get('/journal', ConsultationJournal::class)
        ->middleware('can:voir-journal')
        ->name('journal');

    // Référentiels : chaque écran vérifie son propre droit (EcranReferentiel::onglets()).
    Route::get('/referentiels', function () {
        $route = EcranReferentiel::premierOngletAutorise();
        abort_if($route === null, 403);

        return redirect()->route($route);
    })->name('referentiels');

    Route::prefix('referentiels')->name('referentiels.')->group(function () {
        Route::get('/zones', Zones::class)->middleware('can:gerer-referentiels')->name('zones');
        Route::get('/villages', Villages::class)->middleware('can:gerer-referentiels')->name('villages');
        Route::get('/produits', Produits::class)->middleware('can:gerer-referentiels')->name('produits');
        Route::get('/campagnes', Campagnes::class)->middleware('can:gerer-campagnes')->name('campagnes');
        Route::get('/magasins', Magasins::class)->middleware('can:gerer-referentiels')->name('magasins');
        Route::get('/points-collecte', PointsCollecte::class)->middleware('can:gerer-referentiels')->name('points-collecte');
        Route::get('/parametres', Parametres::class)->middleware('can:gerer-parametres')->name('parametres');
    });

    Route::post('/deconnexion', DeconnexionController::class)->name('logout');
});
