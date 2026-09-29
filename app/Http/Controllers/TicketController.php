<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Decaissement;
use App\Models\MouvementIntrant;
use App\Models\Pret;
use App\Services\Tickets;
use App\Support\Ticket58;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tickets 58 mm à imprimer depuis le bureau avec le pilote de l'imprimante thermique
 * (Xprinter, mini POS 58 mm). Mêmes droits que les PDF correspondants.
 */
class TicketController extends Controller
{
    public function achat(Request $request, Achat $achat): View
    {
        $moi = $request->user();
        abort_unless($moi !== null && ($moi->can('valider-achats') || ($moi->can('saisir-achats') && $achat->cree_par === $moi->id)), 403);

        return $this->page('Bon '.$achat->reference, Tickets::achat($achat), route('achats.bon', $achat));
    }

    public function remise(Pret $pret, string $type, int $id): View
    {
        Gate::authorize('voir-prets');

        $ticket = match ($type) {
            'argent' => Tickets::remiseArgent($pret, Decaissement::query()->findOrFail($id)),
            'intrants' => Tickets::remiseIntrants($pret, MouvementIntrant::query()->findOrFail($id)),
            default => abort(404),
        };

        return $this->page('Reçu '.$pret->reference, $ticket, route('prets.recu-pdf', [$pret, $type, $id]));
    }

    private function page(string $titre, Ticket58 $ticket, string $pdf): View
    {
        return view('impression.ticket-58', ['titre' => $titre, 'lignes' => $ticket->lignes(), 'pdf' => $pdf]);
    }
}
