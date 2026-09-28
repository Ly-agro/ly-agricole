<?php

use App\Http\Controllers\Api\ConnexionController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\TerrainController;
use Illuminate\Support\Facades\Route;

// Appli terrain (D2) : jeton Sanctum par appareil.
Route::post('/connexion', [ConnexionController::class, 'connecter'])->middleware('throttle:20,1')->name('api.connexion');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'deconnecter'])->name('api.deconnexion');
    Route::get('/referentiels', [TerrainController::class, 'referentiels'])->name('api.referentiels');
    Route::post('/sync', [TerrainController::class, 'synchroniser'])->name('api.sync');
    // Photos à part des opérations (fichiers lourds, réseau faible).
    Route::post('/photos', [PhotoController::class, 'recevoir'])->name('api.photos');
});
