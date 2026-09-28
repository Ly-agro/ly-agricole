<?php

namespace App\Models;

use App\Enums\TypeMouvementStock;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒. Créer via App\Services\Stock (verrou du lot, stock ≥ 0).
 *
 * @property int $id
 * @property int $lot_id
 * @property int $magasin_id
 * @property TypeMouvementStock $type
 * @property int $grammes
 * @property Carbon $date_mouvement
 * @property string|null $motif
 * @property string|null $achat_id
 * @property string|null $lien
 * @property int|null $annule_id
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read Lot $lot
 * @property-read Magasin $magasin
 * @property-read User $auteur
 * @property-read MouvementStock|null $contrePassation
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class MouvementStock extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'mouvements_stock';

    protected $guarded = ['id'];

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<Magasin, $this> */
    public function magasin(): BelongsTo
    {
        return $this->belongsTo(Magasin::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Le mouvement qui annule celui-ci, s'il existe.
     *
     * @return HasOne<MouvementStock, $this>
     */
    public function contrePassation(): HasOne
    {
        return $this->hasOne(self::class, 'annule_id');
    }

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementStock::class,
            'grammes' => 'integer',
            'date_mouvement' => 'date',
        ];
    }
}
