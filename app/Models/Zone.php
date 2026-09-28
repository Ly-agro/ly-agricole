<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Région ou département de collecte.
 *
 * @property int $id
 * @property string $nom
 * @property bool $actif
 */
#[Fillable(['nom', 'actif'])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory, Journalise;

    /** @return HasMany<Village, $this> */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
