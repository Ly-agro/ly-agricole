<?php

namespace App\Services\Sms;

/**
 * Envoi d'un SMS (D10). Le fournisseur réel (question 11) sera un autre pilote ; le
 * pilote `journal` écrit le message dans les logs, pour le développement.
 */
interface EnvoyeurSms
{
    /** Nom du pilote, gardé sur chaque confirmation. */
    public function nom(): string;

    /**
     * @param  string  $telephone  10 chiffres (App\Support\Telephone)
     *
     * @throws \RuntimeException si l'envoi échoue
     */
    public function envoyer(string $telephone, string $message): void;
}
