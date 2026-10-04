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
 * Registre immuable 🔒 : décision de la direction sur le plafond de prêt d'un producteur,
 * avec la proposition du logiciel et les données qui l'ont fournie (question 35). La plus
 * récente d'un producteur fait foi ; l'historique reste.
 *
 * @property int $id
 * @property string $producteur_id
 * @property int|null $campagne_id
 * @property int|null $plafond_propose_fcfa
 * @property string $raison_proposition
 * @property int $plafond_retenu_fcfa
 * @property string|null $motif
 * @property array<string, mixed> $donnees
 * @property Carbon $calcule_at
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read Producteur $producteur
 * @property-read Campagne|null $campagne
 * @property-read User $auteur
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class DecisionPlafond extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'decisions_plafond';

    protected $guarded = ['id'];

    /** @return BelongsTo<Producteur, $this> */
    public function producteur(): BelongsTo
    {
        return $this->belongsTo(Producteur::class);
    }

    /** @return BelongsTo<Campagne, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function casts(): array
    {
        return [
            'plafond_propose_fcfa' => 'integer',
            'plafond_retenu_fcfa' => 'integer',
            'donnees' => 'array',
            'calcule_at' => 'datetime',
        ];
    }
}
