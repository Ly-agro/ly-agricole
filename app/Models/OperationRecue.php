<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Dernier sort connu d'une opération du téléphone, par UUID : la clé d'idempotence.
 *
 * @property string $uuid
 * @property string $type
 * @property int $synchronisation_id
 * @property string $appareil_id
 * @property int $user_id
 * @property string $statut accepte | rejete
 * @property string|null $motif
 * @property Carbon|null $cree_at
 * @property Carbon $recu_at
 */
class OperationRecue extends Model
{
    public const ACCEPTE = 'accepte';

    public const DEJA_RECU = 'deja_recu';

    public const REJETE = 'rejete';

    protected $table = 'operations_recues';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['cree_at' => 'datetime', 'recu_at' => 'datetime'];
    }
}
