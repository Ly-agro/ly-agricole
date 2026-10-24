<?php

namespace App\Models;

use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une validation d'un prêt par une personne. Immuable : une validation donnée ne se
 * retire pas en silence.
 *
 * @property int $id
 * @property string $pret_id
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read User $user
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class ValidationPret extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'validations_pret';

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
