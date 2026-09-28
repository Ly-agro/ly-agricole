<?php

namespace App\Models;

use App\Enums\ActionJournal;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒 : une ligne par action. Écrire via App\Services\Journal.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ActionJournal $action
 * @property string|null $objet_type
 * @property string|null $objet_id
 * @property array<string, mixed>|null $avant
 * @property array<string, mixed>|null $apres
 * @property string|null $ip
 * @property string|null $appareil
 * @property Carbon $at
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class JournalActivite extends Model
{
    use Immuable;

    public const CREATED_AT = 'at';

    public const UPDATED_AT = null;

    protected $table = 'journal_activite';

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Valeur d'un champ `avant` / `apres` telle qu'un lecteur de la direction la comprend.
     */
    public static function valeurLisible(mixed $valeur): string
    {
        return match (true) {
            $valeur === null, $valeur === '' => '(vide)',
            $valeur === true => 'oui',
            $valeur === false => 'non',
            is_scalar($valeur) => (string) $valeur,
            default => (string) json_encode($valeur, JSON_UNESCAPED_UNICODE),
        };
    }

    protected function casts(): array
    {
        return [
            'action' => ActionJournal::class,
            'avant' => 'array',
            'apres' => 'array',
            'at' => 'datetime',
        ];
    }
}
