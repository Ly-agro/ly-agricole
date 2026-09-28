<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\MagasinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Magasin de stockage. Capacité en grammes (D4), saisie et affichée en kg.
 *
 * @property int $id
 * @property string $nom
 * @property int $village_id
 * @property int|null $capacite_g
 * @property bool $actif
 * @property-read Village $village
 */
#[Fillable(['nom', 'village_id', 'capacite_g', 'actif'])]
class Magasin extends Model
{
    /** @use HasFactory<MagasinFactory> */
    use HasFactory, Journalise;

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'capacite_g' => 'integer',
        ];
    }
}
