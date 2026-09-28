<?php

namespace App\Models;

use App\Enums\TypeMouvementIntrant;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒. Créer via App\Services\StockIntrants (verrou, stock ≥ 0).
 *
 * @property int $id
 * @property int $intrant_id
 * @property int $magasin_id
 * @property TypeMouvementIntrant $type
 * @property int $quantite
 * @property int|null $valeur_fcfa
 * @property int|null $prix_unitaire_fcfa
 * @property string|null $pret_id
 * @property Carbon $date_mouvement
 * @property string|null $motif
 * @property int|null $annule_id
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read Intrant $intrant
 * @property-read Magasin $magasin
 * @property-read Pret|null $pret
 * @property-read User $auteur
 * @property-read MouvementIntrant|null $contrePassation
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class MouvementIntrant extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'mouvements_intrants';

    protected $guarded = ['id'];

    /** @return BelongsTo<Intrant, $this> */
    public function intrant(): BelongsTo
    {
        return $this->belongsTo(Intrant::class);
    }

    /** @return BelongsTo<Magasin, $this> */
    public function magasin(): BelongsTo
    {
        return $this->belongsTo(Magasin::class);
    }

    /** @return BelongsTo<Pret, $this> */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Le mouvement qui annule celui-ci, s'il existe.
     *
     * @return HasOne<MouvementIntrant, $this>
     */
    public function contrePassation(): HasOne
    {
        return $this->hasOne(self::class, 'annule_id');
    }

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementIntrant::class,
            'quantite' => 'integer',
            'valeur_fcfa' => 'integer',
            'prix_unitaire_fcfa' => 'integer',
            'date_mouvement' => 'date',
        ];
    }
}
