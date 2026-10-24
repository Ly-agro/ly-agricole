<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Pilote de développement : le SMS est écrit dans les logs, rien ne part. */
class EnvoyeurSmsJournal implements EnvoyeurSms
{
    public function nom(): string
    {
        return 'journal';
    }

    public function envoyer(string $telephone, string $message): void
    {
        Log::info('SMS (pilote journal)', ['telephone' => $telephone, 'message' => $message]);
    }
}
