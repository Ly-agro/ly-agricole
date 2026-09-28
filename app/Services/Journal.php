<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Écrit dans le journal d'activité. Appelé dans la même transaction que l'opération
 * journalisée : si l'opération est annulée, sa trace l'est aussi.
 */
class Journal
{
    /**
     * @param  array<string, mixed>|null  $avant
     * @param  array<string, mixed>|null  $apres
     */
    public static function enregistrer(
        ActionJournal $action,
        ?Model $objet = null,
        ?array $avant = null,
        ?array $apres = null,
        ?int $userId = null,
    ): JournalActivite {
        $requete = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return JournalActivite::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'objet_type' => $objet?->getMorphClass(),
            'objet_id' => $objet ? (string) $objet->getKey() : null,
            'avant' => $avant ?: null,
            'apres' => $apres ?: null,
            'ip' => $requete?->ip(),
            'appareil' => $requete ? Str::limit((string) $requete->userAgent(), 250, '') : 'console',
        ]);
    }
}
