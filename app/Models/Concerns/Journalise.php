<?php

namespace App\Models\Concerns;

use App\Enums\ActionJournal;
use App\Services\Journal;
use Illuminate\Database\Eloquent\Model;

/**
 * Chaque création, modification ou suppression du modèle laisse une ligne au journal,
 * avec l'état avant et après (seulement les champs changés pour une modification).
 *
 * Les attributs cachés du modèle (`$hidden`, ex. mot de passe) ne sont jamais copiés :
 * on note seulement qu'ils ont changé. `saveQuietly()` contourne le journal : ne pas
 * l'utiliser sur un modèle journalisé.
 */
trait Journalise
{
    /** Changements qui ne méritent pas une ligne à eux seuls. */
    private const CHAMPS_IGNORES = ['created_at', 'updated_at', 'remember_token'];

    private const MASQUE = '(masqué)';

    public static function bootJournalise(): void
    {
        static::created(function (Model $modele) {
            Journal::enregistrer(
                ActionJournal::Creation,
                $modele,
                apres: self::pourJournal($modele, $modele->getAttributes()),
            );
        });

        static::updated(function (Model $modele) {
            $changes = array_diff_key($modele->getChanges(), array_flip(self::CHAMPS_IGNORES));

            if ($changes === []) {
                return;
            }

            Journal::enregistrer(
                ActionJournal::Modification,
                $modele,
                avant: self::pourJournal($modele, array_intersect_key($modele->getRawOriginal(), $changes)),
                apres: self::pourJournal($modele, $changes),
            );
        });

        static::deleted(function (Model $modele) {
            Journal::enregistrer(
                ActionJournal::Suppression,
                $modele,
                avant: self::pourJournal($modele, $modele->getRawOriginal()),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributs
     * @return array<string, mixed>
     */
    private static function pourJournal(Model $modele, array $attributs): array
    {
        $attributs = array_diff_key($attributs, array_flip(self::CHAMPS_IGNORES));

        foreach ($modele->getHidden() as $cache) {
            if (array_key_exists($cache, $attributs)) {
                $attributs[$cache] = self::MASQUE;
            }
        }

        return $attributs;
    }
}
