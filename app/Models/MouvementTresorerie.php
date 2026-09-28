<?php

namespace App\Models;

use App\Enums\NatureMouvement;
use App\Enums\SensMouvement;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒 (D5). Ne jamais créer directement : passer par
 * App\Services\Tresorerie, qui verrouille le compte et vérifie qu'il ne passe pas en
 * négatif (invariant 3).
 *
 * @property int $id
 * @property int $compte_id
 * @property SensMouvement $sens
 * @property int $montant_fcfa
 * @property NatureMouvement $nature
 * @property Carbon $date_operation
 * @property string $libelle
 * @property string|null $reference_externe
 * @property string|null $lien
 * @property string|null $source_type
 * @property string|null $source_id
 * @property int|null $annule_id
 * @property string|null $motif
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read CompteTresorerie $compte
 * @property-read User $auteur
 * @property-read MouvementTresorerie|null $contrePassation
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class MouvementTresorerie extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'mouvements_tresorerie';

    protected $guarded = ['id'];

    /** Montant signé : + pour une entrée, − pour une sortie. */
    public function montantSigne(): int
    {
        return $this->sens === SensMouvement::Entree ? $this->montant_fcfa : -$this->montant_fcfa;
    }

    /** @return BelongsTo<CompteTresorerie, $this> */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_id');
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Le mouvement qui annule celui-ci, s'il existe.
     *
     * @return HasOne<MouvementTresorerie, $this>
     */
    public function contrePassation(): HasOne
    {
        return $this->hasOne(self::class, 'annule_id');
    }

    protected function casts(): array
    {
        return [
            'sens' => SensMouvement::class,
            'nature' => NatureMouvement::class,
            'montant_fcfa' => 'integer',
            'date_operation' => 'date',
        ];
    }
}
