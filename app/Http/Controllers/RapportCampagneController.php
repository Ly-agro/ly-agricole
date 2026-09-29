<?php

namespace App\Http\Controllers;

use App\Models\Campagne;
use App\Services\RapportCampagne;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Rapports du contrat de campagne (art. 18) en PDF. Envoyé en POST : les « principaux
 * événements » sont un texte libre, qui n'a pas sa place dans une adresse.
 */
class RapportCampagneController extends Controller
{
    public function pointEtape(Request $request): Response
    {
        abort_unless($request->user()?->can('voir-rapport-campagne'), 403);

        $donnees = $request->validate([
            'campagne_id' => ['required', 'integer', Rule::exists('campagnes', 'id')],
            'evenements' => ['nullable', 'string', 'max:'.RapportCampagne::MAX_EVENEMENTS],
        ]);

        return RapportCampagne::pointEtapePdf(
            Campagne::query()->findOrFail($donnees['campagne_id']),
            (string) ($donnees['evenements'] ?? ''),
        );
    }
}
