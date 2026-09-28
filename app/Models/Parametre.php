<?php

namespace App\Models;

use App\Enums\CleParametre;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage modifiable par la direction (seuils…). Lire via `Parametre::entier()`.
 *
 * @property int $id
 * @property CleParametre $cle
 * @property string|null $valeur
 */
#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    use Journalise;

    /**
     * Valeur entière du paramètre, ou null s'il n'est pas défini. Null ne veut PAS
     * dire zéro : à l'appelant d'appliquer la règle prudente (voir CleParametre).
     */
    public static function entier(CleParametre $cle): ?int
    {
        $valeur = static::query()->where('cle', $cle)->value('valeur');

        return $valeur === null || $valeur === '' ? null : (int) $valeur;
    }

    protected function casts(): array
    {
        return ['cle' => CleParametre::class];
    }
}
