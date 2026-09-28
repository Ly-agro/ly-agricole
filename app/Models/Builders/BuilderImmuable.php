<?php

namespace App\Models\Builders;

use App\Exceptions\RegistreImmuableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Constructeur de requêtes d'un registre immuable : lecture et insertion seulement.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class BuilderImmuable extends Builder
{
    public function update(array $values): int
    {
        throw $this->refus('modification');
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int|string, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw $this->refus('modification');
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw $this->refus('modification');
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw $this->refus('modification');
    }

    public function delete(): mixed
    {
        throw $this->refus('suppression');
    }

    public function forceDelete(): mixed
    {
        throw $this->refus('suppression');
    }

    private function refus(string $operation): RegistreImmuableException
    {
        return RegistreImmuableException::pour($this->getModel()->getTable(), $operation);
    }
}
