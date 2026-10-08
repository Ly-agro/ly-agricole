<?php

namespace App\Http\Controllers;

use App\Support\Fichiers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vérification du stockage des fichiers en production (2026-10-07, envois en 500) : quels
 * disques, quelles variables sont posées (oui / non, jamais leur valeur), puis un vrai
 * aller-retour écriture → lecture → suppression. Direction seule. À retirer une fois R2 en marche.
 */
class DiagnosticFichiersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('gerer-parametres');

        $disque = Fichiers::disque();
        $temporaire = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');
        $resultat = [
            'disque_fichiers' => $disque,
            'disque_envois_livewire' => $temporaire,
            'variables_r2' => [
                'R2_ACCESS_KEY_ID' => filled(config('filesystems.disks.r2.key')),
                'R2_SECRET_ACCESS_KEY' => filled(config('filesystems.disks.r2.secret')),
                'R2_BUCKET' => config('filesystems.disks.r2.bucket'),
                'R2_ENDPOINT' => filled(config('filesystems.disks.r2.endpoint')),
            ],
        ];

        foreach (array_unique([$disque, (string) $temporaire]) as $nom) {
            $chemin = 'diagnostic/'.Str::uuid7().'.txt';
            try {
                $stockage = Storage::disk($nom);
                $stockage->put($chemin, 'LY AGRICOLE');
                $relu = $stockage->get($chemin);
                $stockage->delete($chemin);
                $resultat['essais'][$nom] = $relu === 'LY AGRICOLE' ? 'OK : écriture, lecture et suppression réussies' : 'ÉCHEC : relu autre chose';
            } catch (Throwable $e) {
                $resultat['essais'][$nom] = 'ÉCHEC : '.class_basename($e).' — '.Str::limit($e->getMessage(), 600);
            }
        }

        return response()->json($resultat, options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
