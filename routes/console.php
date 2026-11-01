<?php

use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Services\Alertes;
use App\Services\RecuperationActualites;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

// Clés VAPID du Web Push (notifications du bureau) : à copier dans .env, une seule fois.
// Les changer oblige chaque navigateur à se réabonner.
Artisan::command('notifications:cles-vapid', function () {
    $cles = VAPID::createVapidKeys();
    $this->line('PUSH_VAPID_PUBLIQUE='.$cles['publicKey']);
    $this->line('PUSH_VAPID_PRIVEE='.$cles['privateKey']);
    $this->comment('À mettre dans .env (la clé privée ne se partage pas), puis php artisan config:clear.');
})->purpose('Générer les clés VAPID des notifications du bureau');

// Alertes quotidiennes (saisies en attente, prêts en retard, budget, écarts, sauvegardes).
// Une alerte ne part qu'une fois : relancer le même jour ne renvoie rien.
Artisan::command('notifications:alertes', function () {
    foreach (Alertes::executer() as $sorte => $nombre) {
        $this->line(str_pad($sorte, 10).$nombre);
    }
})->purpose('Envoyer les alertes du jour à qui a les droits');

Artisan::command('campagnes:suspendre-expirees', function () {
    $aujourdhui = Carbon::today();
    $ids = Campagne::query()->where('statut', StatutCampagne::Ouverte)
        ->whereDate('fin', '<', $aujourdhui)->orderBy('id')->pluck('id');
    $nombre = 0;

    foreach ($ids as $id) {
        $suspendue = DB::transaction(function () use ($id, $aujourdhui): bool {
            $campagne = Campagne::query()->lockForUpdate()->find($id);
            if ($campagne === null || $campagne->statut !== StatutCampagne::Ouverte || $campagne->fin->greaterThanOrEqualTo($aujourdhui)) {
                return false;
            }

            $campagne->update(['statut' => StatutCampagne::Suspendue]);

            return true;
        });

        $nombre += (int) $suspendue;
    }

    $this->info("{$nombre} campagne(s) suspendue(s). ");
})->purpose('Suspendre les campagnes ouvertes après leur date de fin');

// Actualités : lit les flux choisis par la direction ; tout arrive en brouillon à relire.
Artisan::command('vitrine:actualites', function () {
    $r = RecuperationActualites::toutesLesSources();
    $this->line($r['nouveaux'].' nouveau(x) brouillon(s), '.$r['erreurs'].' source(s) en erreur.');
})->purpose('Récupérer les actualités des flux RSS de la vitrine (en brouillon)');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sauvegardes (semaine 12) : chaque nuit, et chaque dimanche la plus récente est
// restaurée en entier dans une base jetable et comparée. Demande la tâche planifiée du serveur :
// * * * * * cd /chemin && php artisan schedule:run
Schedule::command('ly:sauvegarder')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('ly:verifier-sauvegarde')->weeklyOn(0, '03:00')->withoutOverlapping();
// Alertes : chaque matin, après la sauvegarde (son état fait partie des alertes).
Schedule::command('notifications:alertes')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('campagnes:suspendre-expirees')->dailyAt('00:05')->withoutOverlapping();
// Actualités de la vitrine : chaque matin (brouillons à relire, jamais publiés d'eux-mêmes).
Schedule::command('vitrine:actualites')->dailyAt('06:00')->withoutOverlapping();
