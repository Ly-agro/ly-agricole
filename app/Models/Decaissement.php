<?php

namespace App\Models;

use App\Enums\ModeDecaissement;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒 : une tranche versée au producteur, avec le mouvement de
 * trésorerie qui l'a payée. Une erreur se corrige en contre-passant ce mouvement.
 *
 * @property int $id
 * @property string $pret_id
 * @property int $montant_fcfa
 * @property ModeDecaissement $mode
 * @property string|null $reference_paiement
 * @property int $compte_id
 * @property Carbon $date_decaissement
 * @property string|null $justificatif
 * @property int $mouvement_id
 * @property int $cree_par
 * @property-read Pret $pret
 * @property-read CompteTresorerie $compte
 * @property-read MouvementTresorerie $mouvement
 * @property-read User $auteur
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class Decaissement extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<Pret, $this> */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    /** @return BelongsTo<CompteTresorerie, $this> */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_id');
    }

    /** @return BelongsTo<MouvementTresorerie, $this> */
    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(MouvementTresorerie::class, 'mouvement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function casts(): array
    {
        return [
            'mode' => ModeDecaissement::class,
            'montant_fcfa' => 'integer',
            'date_decaissement' => 'date',
        ];
    }
}
