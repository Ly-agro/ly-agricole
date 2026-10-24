<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\VillageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $zone_id
 * @property string $nom
 * @property string|null $lat
 * @property string|null $lng
 * @property bool $actif
 * @property-read Zone $zone
 */
#[Fillable(['zone_id', 'nom', 'lat', 'lng', 'actif'])]
class Village extends Model
{
    /** @use HasFactory<VillageFactory> */
    use HasFactory, Journalise;

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            // En texte décimal : pas de float, même pour une coordonnée.
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }
}
