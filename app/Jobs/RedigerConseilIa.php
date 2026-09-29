<?php

namespace App\Jobs;

use App\Models\Diagnostic;
use App\Services\Ia\ClientIa;
use App\Services\Ia\Diagnostics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Brouillon de conseil par le service IA (fiches validées seulement, puis contrôle). */
class RedigerConseilIa implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $diagnosticId, public string $observation) {}

    public function handle(ClientIa $client): void
    {
        $d = Diagnostic::query()->find($this->diagnosticId);
        if ($d !== null) {
            Diagnostics::rediger($d, $this->observation, $client);
        }
    }
}
