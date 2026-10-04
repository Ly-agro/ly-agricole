<?php

use App\Providers\AppServiceProvider;
use App\Providers\IaServiceProvider;
use App\Providers\NotificationsServiceProvider;

return [
    AppServiceProvider::class,
    NotificationsServiceProvider::class,
    IaServiceProvider::class,
];
