<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\GroupeProducteurFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe de producteurs d'un village (caution solidaire en phase 2).
 *
 * @property int $id
 * @property string $nom
 * @property int $village_id
 * @property string|null $responsable_id
 * @property bool $actif
 * @property-read Village $village
 * @property-read Producteur|null $responsable
 */
#[Fillable(['nom', 'village_id', 'responsable_id', 'actif'])]
class GroupeProducteur extends Model
{
    /** @use HasFactory<GroupeProducteurFactory> */
    use HasFactory, Journalise;

    protected $table = 'groupes_producteurs';

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** @return BelongsTo<Producteur, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Producteur::class, 'responsable_id');
    }

    /** @return HasMany<Producteur, $this> */
    public function membres(): HasMany
    {
        return $this->hasMany(Producteur::class, 'groupe_id');
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
