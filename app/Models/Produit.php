<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\ProduitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anacarde, karité, tomate… Tous se pèsent : les quantités sont en grammes (D4).
 *
 * @property int $id
 * @property string $code
 * @property string $nom
 * @property bool $actif
 */
#[Fillable(['code', 'nom', 'actif'])]
class Produit extends Model
{
    /** @use HasFactory<ProduitFactory> */
    use HasFactory, Journalise;

    /** @return HasMany<Campagne, $this> */
    public function campagnes(): HasMany
    {
        return $this->hasMany(Campagne::class);
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
