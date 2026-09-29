<?php

namespace App\Models;

use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒 : argent réellement reçu pour une vente (montant signé, une
 * contre-passation est négative). Encaissé = Σ montant_fcfa ; reste à encaisser =
 * vente.montant_fcfa − encaissé, jamais négatif (le service plafonne / refuse).
 *
 * @property int $id
 * @property string $vente_id
 * @property int $montant_fcfa
 * @property int $compte_id
 * @property Carbon $date_encaissement
 * @property string|null $reference_paiement
 * @property int|null $mouvement_id
 * @property string|null $motif
 * @property int|null $annule_id
 * @property int $cree_par
 * @property-read Vente $vente
 * @property-read CompteTresorerie $compte
 * @property-read MouvementTresorerie|null $mouvement
 * @property-read User $auteur
 * @property-read Encaissement|null $contrePassation
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class Encaissement extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<Vente, $this> */
    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
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

    /**
     * La contre-passation de cet encaissement, s'il y en a une.
     *
     * @return HasOne<Encaissement, $this>
     */
    public function contrePassation(): HasOne
    {
        return $this->hasOne(self::class, 'annule_id');
    }

    protected function casts(): array
    {
        return [
            'montant_fcfa' => 'integer',
            'date_encaissement' => 'date',
        ];
    }
}
