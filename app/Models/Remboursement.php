<?php

namespace App\Models;

use App\Enums\RegleValorisationNature;
use App\Enums\TypeRemboursement;
use App\Models\Builders\BuilderImmuable;
use App\Models\Concerns\Immuable;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Registre immuable 🔒 : ce qu'un producteur a rendu sur son prêt, en argent ou en
 * kilos (valorisés selon la règle choisie, figée ici). Restant dû = remis − Σ montants.
 *
 * @property int $id
 * @property string $pret_id
 * @property TypeRemboursement $type
 * @property int $montant_fcfa
 * @property int|null $grammes
 * @property int|null $prix_kg_fcfa
 * @property RegleValorisationNature|null $regle_valorisation
 * @property string|null $achat_id
 * @property int|null $mouvement_id
 * @property Carbon $date_remboursement
 * @property string|null $motif
 * @property int|null $annule_id
 * @property int $cree_par
 * @property-read Pret $pret
 * @property-read Achat|null $achat
 * @property-read User $auteur
 * @property-read Remboursement|null $contrePassation
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class Remboursement extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<Pret, $this> */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    /** @return BelongsTo<Achat, $this> */
    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * La contre-passation de ce remboursement, s'il y en a une.
     *
     * @return HasOne<Remboursement, $this>
     */
    public function contrePassation(): HasOne
    {
        return $this->hasOne(self::class, 'annule_id');
    }

    protected function casts(): array
    {
        return [
            'type' => TypeRemboursement::class,
            'regle_valorisation' => RegleValorisationNature::class,
            'montant_fcfa' => 'integer',
            'grammes' => 'integer',
            'prix_kg_fcfa' => 'integer',
            'date_remboursement' => 'date',
        ];
    }
}
