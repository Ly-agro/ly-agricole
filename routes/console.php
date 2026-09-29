<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sauvegardes (semaine 12) : chaque nuit, et chaque dimanche la plus récente est
// restaurée en entier dans une base jetable et comparée. Demande la tâche planifiée du serveur :
// * * * * * cd /chemin && php artisan schedule:run
Schedule::command('ly:sauvegarder')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('ly:verifier-sauvegarde')->weeklyOn(0, '03:00')->withoutOverlapping();
