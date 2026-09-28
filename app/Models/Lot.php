<?php

namespace App\Models;

use App\Enums\StatutLot;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot : quantité homogène d'un produit suivie de l'achat à la revente. Pas de colonne
 * stock : stock = Σ mouvements_stock.grammes (invariant 2). Écrire via App\Services\Stock.
 *
 * @property int $id
 * @property string $code
 * @property int $produit_id
 * @property int $campagne_id
 * @property int $magasin_id
 * @property StatutLot $statut
 * @property string|null $description
 * @property int $cree_par
 * @property-read Produit $produit
 * @property-read Campagne $campagne
 * @property-read Magasin $magasin
 */
#[Fillable(['code', 'produit_id', 'campagne_id', 'magasin_id', 'statut', 'description', 'cree_par'])]
class Lot extends Model
{
    use Journalise;

    public const PREFIXE_CODE = 'LOT-';

    protected static function booted(): void
    {
        static::creating(function (Lot $lot) {
            $lot->code ??= self::PREFIXE_CODE.str_pad((string) Compteur::suivant('lot'), 5, '0', STR_PAD_LEFT);
        });
    }

    /** Stock en grammes, dans un magasin ou au total. */
    public function stock(?int $magasinId = null): int
    {
        return (int) $this->mouvements()
            ->when($magasinId !== null, fn ($q) => $q->where('magasin_id', $magasinId))
            ->sum('grammes');
    }

    /** @return HasMany<MouvementStock, $this> */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    /** @return BelongsTo<Magasin, $this> */
    public function magasin(): BelongsTo
    {
        return $this->belongsTo(Magasin::class);
    }

    protected function casts(): array
    {
        return ['statut' => StatutLot::class];
    }
}
