<?php

namespace App\Providers;

use App\Models\Achat;
use App\Models\Campagne;
use App\Models\Depense;
use App\Models\Pret;
use App\Models\Vente;
use App\Observers\DeclencheursCampagne;
use App\Observers\DeclencheursNotifications;
use Illuminate\Support\ServiceProvider;

/**
 * Notifications (phase 2, session B) : branchées par observateurs, sans toucher aux
 * services qui écrivent les achats, dépenses, prêts et ventes.
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([Achat::class, Depense::class, Pret::class, Vente::class] as $modele) {
            $modele::observe(DeclencheursNotifications::class);
        }
        Campagne::observe(DeclencheursCampagne::class);
    }
}
