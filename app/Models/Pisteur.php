<?php

namespace App\Models;

use App\Enums\ModeCommission;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pisteur : intermédiaire qui vend ce qu'il a collecté. Sa commission attend la
 * question ouverte n° 6.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $telephone
 * @property ModeCommission|null $commission_mode règle de commission : vide tant que la direction ne l'a pas choisie
 * @property int|null $commission_valeur FCFA par kilo, ou pour mille du montant, selon le mode
 * @property bool $actif
 */
#[Fillable(['nom', 'telephone', 'commission_mode', 'commission_valeur', 'actif'])]
class Pisteur extends Model
{
    use Journalise;

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'commission_mode' => ModeCommission::class, 'commission_valeur' => 'integer'];
    }
}
