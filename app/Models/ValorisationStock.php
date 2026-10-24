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
 * Registre immuable 🔒 : valorisation du stock invendu d'une campagne (contrat art. 11.3),
 * à partir de deux offres de prix écrites. La plus récente d'une campagne fait foi.
 *
 * @property int $id
 * @property int $campagne_id
 * @property int $poids_g
 * @property string $offre1_fournisseur
 * @property Carbon $offre1_date
 * @property int $offre1_prix_kg_fcfa
 * @property string $offre2_fournisseur
 * @property Carbon $offre2_date
 * @property int $offre2_prix_kg_fcfa
 * @property int $valeur_retenue_fcfa
 * @property string|null $motif
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read Campagne $campagne
 * @property-read User $auteur
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class ValorisationStock extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'valorisations_stock';

    protected $guarded = ['id'];

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
            'poids_g' => 'integer',
            'offre1_date' => 'date',
            'offre2_date' => 'date',
            'offre1_prix_kg_fcfa' => 'integer',
            'offre2_prix_kg_fcfa' => 'integer',
            'valeur_retenue_fcfa' => 'integer',
        ];
    }
}
