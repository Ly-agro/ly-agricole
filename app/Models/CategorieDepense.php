<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nom
 * @property string|null $code_syscohada
 * @property bool $exclue_fonds_campagne
 * @property bool $actif
 */
#[Fillable(['nom', 'code_syscohada', 'exclue_fonds_campagne', 'actif'])]
class CategorieDepense extends Model
{
    use Journalise;

    protected $table = 'categories_depense';

    protected function casts(): array
    {
        return [
            'exclue_fonds_campagne' => 'boolean',
            'actif' => 'boolean',
        ];
    }
}
