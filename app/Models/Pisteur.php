<?php

namespace App\Models;

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
 * @property bool $actif
 */
#[Fillable(['nom', 'telephone', 'actif'])]
class Pisteur extends Model
{
    use Journalise;

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
