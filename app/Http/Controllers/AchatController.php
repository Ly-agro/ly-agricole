<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Services\BonAchat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Même visibilité que la liste des achats : le valideur voit tout, l'agent ses achats. */
class AchatController extends Controller
{
    public function bon(Request $request, Achat $achat): Response
    {
        $moi = $request->user();
        abort_unless($moi !== null && ($moi->can('valider-achats') || ($moi->can('saisir-achats') && $achat->cree_par === $moi->id)), 403);

        return BonAchat::pdf($achat);
    }
}
