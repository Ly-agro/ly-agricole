<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Photo prise sur le terrain (pesée, justificatif de dépense), envoyée à part des
 * opérations. Son UUID vient du téléphone ; elle est sur le disque privé.
 *
 * @property string $id
 * @property int $user_id
 * @property string $appareil_id
 * @property string $chemin
 * @property string $mime
 * @property int $taille_octets
 * @property Carbon|null $prise_at
 * @property string|null $lat
 * @property string|null $lng
 * @property Carbon $recu_at
 * @property-read User $user
 */
class PhotoTerrain extends Model
{
    protected $table = 'photos_terrain';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'prise_at' => 'datetime',
            'recu_at' => 'datetime',
            'taille_octets' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }
}
