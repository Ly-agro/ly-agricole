<?php

namespace App\Jobs;

use App\Models\ConfirmationSms;
use App\Services\Sms\EnvoyeurSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envoie une confirmation déjà enregistrée. Mis en file APRÈS le commit de l'opération
 * (skill argent-et-kilos §3) : un SMS ne part jamais pour une opération annulée.
 */
class EnvoyerConfirmationSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $confirmationId)
    {
        $this->afterCommit();
    }

    public function handle(EnvoyeurSms $envoyeur): void
    {
        $confirmation = ConfirmationSms::query()->find($this->confirmationId);
        if ($confirmation === null || $confirmation->statut === ConfirmationSms::ENVOYE) {
            return;
        }

        try {
            $envoyeur->envoyer($confirmation->telephone, $confirmation->message);
            $confirmation->update(['statut' => ConfirmationSms::ENVOYE, 'envoye_at' => now(), 'pilote' => $envoyeur->nom(), 'erreur' => null]);
        } catch (Throwable $e) {
            $confirmation->update(['statut' => ConfirmationSms::ECHEC, 'erreur' => mb_substr($e->getMessage(), 0, 250)]);
            throw $e;
        }
    }
}
