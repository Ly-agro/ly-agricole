<?php

namespace App\Jobs;

use App\Models\Diagnostic;
use App\Services\Ia\ClientIa;
use App\Services\Ia\Diagnostics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Diagnostic d'une photo par le service IA : jamais pendant une requête d'utilisateur. */
class TraiterDiagnosticIa implements ShouldQueue
{
    use Queueable;

    /** Un modèle local peut être lent : on attend, sans relancer à l'aveugle. */
    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $diagnosticId) {}

    public function handle(ClientIa $client): void
    {
        $d = Diagnostic::query()->find($this->diagnosticId);
        if ($d !== null) {
            Diagnostics::traiter($d, $client);
        }
    }
}
