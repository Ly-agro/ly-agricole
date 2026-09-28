<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepenseController extends Controller
{
    /** Justificatif sur le disque privé : son auteur, ou qui valide les dépenses. */
    public function justificatif(Depense $depense): StreamedResponse
    {
        abort_unless($depense->cree_par === auth()->id() || Gate::allows('valider-depenses'), 403);
        abort_unless(Storage::disk('local')->exists($depense->justificatif), 404);

        return Storage::disk('local')->response($depense->justificatif, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
