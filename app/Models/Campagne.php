<?php

namespace App\Models;

use App\Enums\StatutCampagne;
use App\Models\Concerns\Journalise;
use Database\Factories\CampagneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Saison de commercialisation d'un produit (code `2026-2027`). Le prix officiel
 * bord-champ est propre à chaque campagne : jamais une constante du code.
 *
 * @property int $id
 * @property int $produit_id
 * @property string $code
 * @property Carbon $debut
 * @property Carbon $fin
 * @property StatutCampagne $statut
 * @property int|null $prix_officiel_kg_fcfa
 * @property-read Produit $produit
 */
#[Fillable(['produit_id', 'code', 'debut', 'fin', 'statut', 'prix_officiel_kg_fcfa'])]
class Campagne extends Model
{
    /** @use HasFactory<CampagneFactory> */
    use HasFactory, Journalise;

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    protected function casts(): array
    {
        return [
            'debut' => 'date',
            'fin' => 'date',
            'statut' => StatutCampagne::class,
            'prix_officiel_kg_fcfa' => 'integer',
        ];
    }
}
