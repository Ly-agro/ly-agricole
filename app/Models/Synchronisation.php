<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un appel de l'appli terrain à /api/sync (skill terrain-hors-ligne) : qui, quel
 * appareil, quand, et le sort des opérations.
 *
 * @property int $id
 * @property string $appareil_id
 * @property int $user_id
 * @property Carbon $recu_at
 * @property int $nb_operations
 * @property int $nb_acceptees
 * @property int $nb_deja_recues
 * @property int $nb_rejetees
 * @property-read User $user
 */
class Synchronisation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OperationRecue, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(OperationRecue::class);
    }

    protected function casts(): array
    {
        return ['recu_at' => 'datetime'];
    }
}
