<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte désactivé pendant qu'il est connecté perd sa session à la requête suivante,
 * sans attendre qu'elle expire.
 */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->actif) {
            // Appli terrain (jeton, sans session) : refus net, le jeton ne sert plus à rien.
            if (! $request->hasSession()) {
                return response()->json(['message' => 'Ce compte a été désactivé.'], 401);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Ce compte a été désactivé.']);
        }

        return $next($request);
    }
}
