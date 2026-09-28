<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\PointCollecteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lieu où les agents pèsent et achètent.
 *
 * @property int $id
 * @property string $nom
 * @property int $village_id
 * @property bool $actif
 * @property-read Village $village
 */
#[Fillable(['nom', 'village_id', 'actif'])]
class PointCollecte extends Model
{
    /** @use HasFactory<PointCollecteFactory> */
    use HasFactory, Journalise;

    protected $table = 'points_collecte';

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
