<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Langue de préférence d'un producteur pour les messages (question 12). Le français est la
 * langue par défaut d'un producteur qui n'en a pas choisi ; aucune langue locale n'est
 * pré-remplie. Pas de suppression : champ `actif`.
 *
 * @property int $id
 * @property string $code
 * @property string $nom
 * @property bool $actif
 */
#[Fillable(['code', 'nom', 'actif'])]
class Langue extends Model
{
    use Journalise;

    /** @return HasMany<Producteur, $this> */
    public function producteurs(): HasMany
    {
        return $this->hasMany(Producteur::class);
    }

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
