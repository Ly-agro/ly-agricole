<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use App\Services\Geo\Contour;
use Database\Factories\ParcelleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parcelle 📱 : UUID v7 (D3). `surface_m2` est recalculée à chaque changement de
 * contour, ici et nulle part ailleurs : elle ne peut pas être saisie.
 *
 * @property string $id
 * @property string $producteur_id
 * @property string $nom
 * @property array{type: string, coordinates: array<mixed>}|null $contour
 * @property int|null $surface_m2
 * @property int|null $produit_id
 * @property int|null $annee_plantation
 * @property int|null $nb_arbres
 * @property string|null $sol
 * @property bool|null $acces_eau
 * @property int $cree_par
 * @property bool $actif
 * @property-read Producteur $producteur
 * @property-read Produit|null $produit
 */
#[Fillable(['id', 'producteur_id', 'nom', 'contour', 'produit_id', 'annee_plantation', 'nb_arbres', 'sol', 'acces_eau', 'cree_par', 'actif'])]
class Parcelle extends Model
{
    /** @use HasFactory<ParcelleFactory> */
    use HasFactory, HasUuids, Journalise;

    protected static function booted(): void
    {
        static::saving(function (Parcelle $parcelle) {
            if ($parcelle->isDirty('contour')) {
                $parcelle->surface_m2 = $parcelle->contour === null
                    ? null
                    : Contour::depuisGeometrie($parcelle->contour)->surfaceM2();
            }
        });
    }

    public function formeContour(): ?Contour
    {
        return $this->contour === null ? null : Contour::depuisGeometrie($this->contour);
    }

    /** @return BelongsTo<Producteur, $this> */
    public function producteur(): BelongsTo
    {
        return $this->belongsTo(Producteur::class);
    }

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    protected function casts(): array
    {
        return [
            'contour' => 'array',
            'surface_m2' => 'integer',
            'annee_plantation' => 'integer',
            'nb_arbres' => 'integer',
            'acces_eau' => 'boolean',
            'actif' => 'boolean',
        ];
    }
}
