<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un appareil qui reçoit les notifications « push » d'un utilisateur : un navigateur
 * (`web`) ou l'appli terrain (`fcm`). Écrit par App\Services\Notifications.
 *
 * @property int $id
 * @property int $user_id
 * @property string $canal web | fcm
 * @property string $destination adresse Web Push ou jeton FCM
 * @property string $empreinte sha256 de la destination
 * @property string|null $cle_p256dh
 * @property string|null $cle_auth
 * @property string|null $appareil
 * @property Carbon|null $dernier_envoi_at
 * @property int $echecs
 * @property-read User $user
 */
#[Fillable(['user_id', 'canal', 'destination', 'empreinte', 'cle_p256dh', 'cle_auth', 'appareil', 'dernier_envoi_at', 'echecs'])]
class AbonnementPush extends Model
{
    public const WEB = 'web';

    public const FCM = 'fcm';

    /** Au-delà, l'appareil est oublié (désinstallé, navigateur réinitialisé…). */
    public const ECHECS_MAX = 5;

    protected $table = 'abonnements_push';

    protected $hidden = ['destination', 'cle_p256dh', 'cle_auth'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'dernier_envoi_at' => 'datetime',
            'echecs' => 'integer',
        ];
    }
}
