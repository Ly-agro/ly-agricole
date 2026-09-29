<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionJournal;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connexion de l'appli terrain : un jeton Sanctum par appareil (D2). Mêmes règles que
 * l'écran du bureau : compte actif, 5 essais puis blocage, même message pour un compte
 * désactivé que pour un mauvais mot de passe.
 */
class ConnexionController extends Controller
{
    public const TENTATIVES_MAX = 5;

    public function connecter(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'appareil' => ['required', 'string', 'max:100'],
        ]);

        $cle = Str::transliterate(Str::lower($donnees['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($cle, self::TENTATIVES_MAX)) {
            event(new Lockout($request));
            Journal::enregistrer(ActionJournal::BlocageConnexion, apres: ['email' => $donnees['email'], 'appareil' => $donnees['appareil']]);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($cle)]),
            ]);
        }

        $identifiants = ['email' => $donnees['email'], 'password' => $donnees['password'], 'actif' => true];
        if (! Auth::guard('web')->validate($identifiants)) {
            RateLimiter::hit($cle);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        RateLimiter::clear($cle);

        /** @var User $user */
        $user = User::query()->where('email', $donnees['email'])->firstOrFail();
        $jeton = $user->createToken($donnees['appareil']);
        Journal::enregistrer(ActionJournal::Connexion, $user, apres: ['appareil' => $donnees['appareil'], 'canal' => 'api'], userId: $user->id);

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'utilisateur' => ['id' => $user->id, 'nom' => $user->nom, 'role' => $user->role->value],
        ]);
    }

    public function deconnecter(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();
        Journal::enregistrer(ActionJournal::Deconnexion, $user, apres: ['canal' => 'api'], userId: $user->id);

        return response()->json(['statut' => 'deconnecte']);
    }
}
