<?php

namespace App\Notifications\Canaux;

use App\Models\AbonnementPush;
use App\Services\Push\ExpediteurPush;
use App\Services\Push\WebPushVapid;

/** Navigateurs du bureau (Web Push). */
class WebPushCanal extends CanalPush
{
    protected function canal(): string
    {
        return AbonnementPush::WEB;
    }

    protected function expediteur(): ExpediteurPush
    {
        return app(WebPushVapid::class);
    }
}
