<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OperationRefusee;
use App\Http\Controllers\Controller;
use App\Models\AbonnementPush;
use App\Models\User;
use App\Services\Notifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * L'appli terrain déclare le jeton Firebase de son téléphone (à la connexion, et quand
 * Firebase le renouvelle) ; elle le retire à la déconnexion.
 */
class PushController extends Controller
{
    public function abonner(Request $request): JsonResponse
    {
        $d = $request->validate([
            'jeton' => ['required', 'string', 'max:4096'],
            'appareil' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        try {
            Notifications::abonner($user, AbonnementPush::FCM, $d['jeton'], appareil: $d['appareil'] ?? null);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['jeton' => $e->getMessage()]);
        }

        return response()->json(['statut' => 'ok']);
    }

    public function desabonner(Request $request): JsonResponse
    {
        $d = $request->validate(['jeton' => ['required', 'string', 'max:4096']]);
        /** @var User $user */
        $user = $request->user();
        Notifications::desabonner($user, $d['jeton']);

        return response()->json(['statut' => 'ok']);
    }
}
