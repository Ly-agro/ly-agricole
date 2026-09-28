<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use App\Models\Pret;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pièces des prêts, sur le disque privé : seulement pour qui voit les prêts. */
class PretController extends Controller
{
    public function accord(Pret $pret): StreamedResponse
    {
        return $this->servir($pret->accord_ecrit);
    }

    public function recu(Decaissement $decaissement): StreamedResponse
    {
        return $this->servir($decaissement->justificatif);
    }

    private function servir(?string $chemin): StreamedResponse
    {
        Gate::authorize('voir-prets');
        abort_if($chemin === null || ! Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
