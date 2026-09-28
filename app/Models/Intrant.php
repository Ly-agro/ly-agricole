<?php

namespace App\Models;

use App\Enums\UniteIntrant;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Engrais, sacs, bâches, produits… Pas de colonne stock : le stock est la somme des
 * mouvements (D5). Écrire les mouvements via App\Services\StockIntrants.
 *
 * @property int $id
 * @property string $nom
 * @property UniteIntrant $unite
 * @property int $prix_unitaire_fcfa
 * @property bool $actif
 */
#[Fillable(['nom', 'unite', 'prix_unitaire_fcfa', 'actif'])]
class Intrant extends Model
{
    use Journalise;

    public function stock(?int $magasinId = null): int
    {
        return (int) $this->mouvements()
            ->when($magasinId !== null, fn ($q) => $q->where('magasin_id', $magasinId))
            ->sum('quantite');
    }

    /** @return HasMany<MouvementIntrant, $this> */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementIntrant::class);
    }

    protected function casts(): array
    {
        return [
            'unite' => UniteIntrant::class,
            'prix_unitaire_fcfa' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
