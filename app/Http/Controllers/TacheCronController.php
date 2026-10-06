<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Sur Vercel, pas de `schedule:run` : Vercel Cron appelle ces adresses (vercel.json).
 * Seules les commandes listées ici sont joignables, et seulement avec CRON_SECRET.
 */
class TacheCronController extends Controller
{
    /** @var array<string, string> adresse => commande Artisan */
    public const TACHES = [
        'alertes' => 'notifications:alertes',
    ];

    public function __invoke(Request $request, string $tache): JsonResponse
    {
        $secret = config('services.cron.secret');
        $jeton = $request->bearerToken();

        if (! is_string($secret) || ! is_string($jeton) || ! hash_equals($secret, $jeton)) {
            abort(403);
        }

        abort_unless(array_key_exists($tache, self::TACHES), 404);

        $code = Artisan::call(self::TACHES[$tache]);

        return response()->json([
            'tache' => $tache,
            'code' => $code,
            'sortie' => trim(Artisan::output()),
        ], $code === 0 ? 200 : 500);
    }
}
