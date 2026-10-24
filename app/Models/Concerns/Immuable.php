<?php

namespace App\Models\Concerns;

use App\Exceptions\RegistreImmuableException;
use App\Models\Builders\BuilderImmuable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use ReflectionClass;

/**
 * Registre immuable (D5) : on insère, on ne modifie ni ne supprime jamais.
 *
 * Bloque le chemin du modèle (`save`, `update`, `delete`, y compris sans événements :
 * `saveQuietly`, `withoutEvents`). Le modèle doit AUSSI porter
 * `#[UseEloquentBuilder(BuilderImmuable::class)]`, qui bloque le constructeur de
 * requêtes (`query()->update()`, `->delete()`…) ; vérifié au démarrage du modèle.
 * Une requête SQL brute (`DB::table()`) reste possible : ne jamais en écrire sur ces
 * tables.
 */
trait Immuable
{
    public static function bootImmuable(): void
    {
        $attributs = (new ReflectionClass(static::class))->getAttributes(UseEloquentBuilder::class);

        if (($attributs[0] ?? null)?->newInstance()->builderClass !== BuilderImmuable::class) {
            throw new LogicException(static::class.' utilise Immuable sans #[UseEloquentBuilder(BuilderImmuable::class)].');
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        throw RegistreImmuableException::pour($this->getTable(), 'modification');
    }

    protected function performDeleteOnModel(): void
    {
        throw RegistreImmuableException::pour($this->getTable(), 'suppression');
    }
}
