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
 * Registre immuable 🔒 : un prix bord-champ AFFICHÉ sur la vitrine (café, cacao, anacarde…), daté et
 * sourcé. Ce n'est PAS le prix officiel d'une campagne (celui-là est `campagnes.prix_officiel_kg_fcfa`,
 * plancher des achats). Le plus récent d'un produit (date d'effet, puis identifiant) fait foi.
 *
 * @property int $id
 * @property int $produit_id
 * @property int $prix_kg_fcfa
 * @property Carbon $date_effet
 * @property string $source
 * @property string|null $source_url
 * @property string|null $note
 * @property int $cree_par
 * @property Carbon $created_at
 * @property-read Produit $produit
 * @property-read User $auteur
 */
#[UseEloquentBuilder(BuilderImmuable::class)]
class PrixMarche extends Model
{
    use Immuable, Journalise;

    public const UPDATED_AT = null;

    protected $table = 'prix_marche';

    protected $guarded = ['id'];

    /** @return BelongsTo<Produit, $this> */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function casts(): array
    {
        return ['prix_kg_fcfa' => 'integer', 'date_effet' => 'date'];
    }
}
