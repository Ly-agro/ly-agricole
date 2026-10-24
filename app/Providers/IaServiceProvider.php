<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Diagnostic;
use App\Models\FicheTraitement;
use App\Models\User;
use App\Services\Ia\ClientIa;
use App\Services\Ia\ServiceIaHttp;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * IA (phase 3, session B) : droits, client du service ia/ et alias des modèles, à part de
 * AppServiceProvider (fichier partagé entre les sessions).
 */
class IaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClientIa::class, ServiceIaHttp::class);
    }

    public function boot(): void
    {
        // Ajouté à la carte imposée par AppServiceProvider (journal d'activité).
        Relation::morphMap([
            'fiche_traitement' => FicheTraitement::class,
            'diagnostic' => Diagnostic::class,
        ]);

        // Référentiel des traitements et validation des diagnostics : l'agronome seul
        // (skill ly-agricole-ia-conseil). Sans agronome, rien n'est validé ni conseillé.
        Gate::define('gerer-referentiel-traitements', fn (User $user) => $user->aLeRole(Role::Agronome));
        Gate::define('valider-diagnostics', fn (User $user) => $user->aLeRole(Role::Agronome));
        // Voir les diagnostics et le référentiel ; demander un avis sur une visite.
        Gate::define('voir-ia', fn (User $user) => $user->aLeRole(Role::Direction, Role::Agronome));
        Gate::define('demander-avis-ia', fn (User $user) => $user->aLeRole(Role::Direction, Role::Agronome, Role::Agent));
    }
}
