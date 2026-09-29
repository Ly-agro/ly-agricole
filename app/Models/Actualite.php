<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Actualité de la vitrine publique, écrite par la direction. Texte simple (jamais du HTML, affiché
 * échappé). Brouillon tant que `publie` est faux. Pas de suppression : on dépublie.
 *
 * @property int $id
 * @property string $titre
 * @property string $contenu
 * @property bool $publie
 * @property Carbon|null $publie_le
 * @property int $cree_par
 * @property-read User $auteur
 */
#[Fillable(['titre', 'contenu', 'publie', 'publie_le', 'cree_par'])]
class Actualite extends Model
{
    use Journalise;

    /**
     * Ce que le public voit : publié, et pas daté du futur.
     *
     * @param  Builder<Actualite>  $requete
     * @return Builder<Actualite>
     */
    public function scopeVisibles(Builder $requete): Builder
    {
        return $requete->where('publie', true)->whereNotNull('publie_le')->whereDate('publie_le', '<=', Carbon::today()->toDateString());
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function casts(): array
    {
        return ['publie' => 'boolean', 'publie_le' => 'date'];
    }
}
