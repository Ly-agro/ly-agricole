<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Flux RSS / Atom d'actualités choisi par la direction. Pas de suppression : on désactive.
 *
 * @property int $id
 * @property string $nom
 * @property string $url
 * @property bool $actif
 * @property Carbon|null $derniere_recuperation_at
 * @property string|null $dernier_statut
 * @property string|null $dernier_message
 * @property int $dernier_nb
 * @property int $cree_par
 * @property-read User $auteur
 */
#[Fillable(['nom', 'url', 'actif', 'derniere_recuperation_at', 'dernier_statut', 'dernier_message', 'dernier_nb', 'cree_par'])]
class SourceActualites extends Model
{
    use Journalise;

    protected $table = 'sources_actualites';

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'derniere_recuperation_at' => 'datetime', 'dernier_nb' => 'integer'];
    }
}
