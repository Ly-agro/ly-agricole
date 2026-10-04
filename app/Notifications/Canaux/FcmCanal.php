<?php

namespace App\Notifications\Canaux;

use App\Models\AbonnementPush;
use App\Services\Push\ExpediteurPush;
use App\Services\Push\FirebaseFcm;

/** Appli terrain sur le téléphone (Firebase Cloud Messaging). */
class FcmCanal extends CanalPush
{
    protected function canal(): string
    {
        return AbonnementPush::FCM;
    }

    protected function expediteur(): ExpediteurPush
    {
        return app(FirebaseFcm::class);
    }
}
