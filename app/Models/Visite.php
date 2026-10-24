<?php

namespace App\Models;

use App\Enums\PratiqueCulturale;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Visite de parcelle 📱 (UUID v7 du téléphone). Créée par App\Services\Synchronisation ;
 * les photos arrivent avant la fiche et y sont rattachées par leur UUID.
 *
 * @property string $id
 * @property string $parcelle_id
 * @property Carbon $date_visite
 * @property string|null $lat
 * @property string|null $lng
 * @property list<string>|null $pratiques
 * @property string|null $observations
 * @property int $cree_par
 * @property Carbon|null $cree_at
 * @property Carbon $created_at
 * @property-read Parcelle $parcelle
 * @property-read User $auteur
 */
#[Fillable(['id', 'parcelle_id', 'date_visite', 'lat', 'lng', 'pratiques', 'observations', 'cree_par', 'cree_at'])]
class Visite extends Model
{
    use HasUuids, Journalise;

    /** @return list<PratiqueCulturale> */
    public function pratiquesConstatees(): array
    {
        return array_values(array_filter(array_map(
            fn (string $code) => PratiqueCulturale::tryFrom($code),
            $this->pratiques ?? [],
        )));
    }

    /** @return BelongsTo<Parcelle, $this> */
    public function parcelle(): BelongsTo
    {
        return $this->belongsTo(Parcelle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** @return BelongsToMany<PhotoTerrain, $this> */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(PhotoTerrain::class, 'visite_photo', 'visite_id', 'photo_id');
    }

    protected function casts(): array
    {
        return [
            'date_visite' => 'date',
            'pratiques' => 'array',
            'cree_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }
}
