<?php

use App\Http\Controllers\Auth\DeconnexionController;
use App\Livewire\Auth\Connexion;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tableau-de-bord');

// Nommée `login` : c'est la route où Laravel renvoie un visiteur non connecté.
Route::get('/connexion', Connexion::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::view('/tableau-de-bord', 'tableau-de-bord')->name('tableau-de-bord');

    Route::post('/deconnexion', DeconnexionController::class)->name('logout');
});
