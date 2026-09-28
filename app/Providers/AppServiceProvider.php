<?php

namespace App\Providers;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Models\Campagne;
use App\Models\GroupeProducteur;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Parcelle;
use App\Models\PointCollecte;
use App\Models\Producteur;
use App\Models\Produit;
use App\Models\User;
use App\Models\Village;
use App\Models\Zone;
use App\Services\Journal;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->nommerLesObjets();
        $this->definirLesDroits();
        $this->journaliserLesConnexions();
    }

    /**
     * Nom court et stable de chaque modèle dans les colonnes `*_type` (journal, etc.) :
     * renommer une classe ne doit pas rendre le journal illisible. Tout modèle
     * référencé ainsi doit être déclaré ici, sinon Laravel lève une exception.
     */
    private function nommerLesObjets(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'zone' => Zone::class,
            'village' => Village::class,
            'produit' => Produit::class,
            'campagne' => Campagne::class,
            'magasin' => Magasin::class,
            'point_collecte' => PointCollecte::class,
            'parametre' => Parametre::class,
            'producteur' => Producteur::class,
            'groupe_producteurs' => GroupeProducteur::class,
            'parcelle' => Parcelle::class,
        ]);
    }

    /**
     * Qui a le droit de quoi, en un seul endroit. Pas de `Gate::before` pour l'admin :
     * il gère les comptes, il ne valide ni prêt ni dépense (séparation des tâches, D6).
     * Matrice proposée, à faire valider par le responsable projet
     * (docs/QUESTIONS_OUVERTES.md, n° 19).
     */
    private function definirLesDroits(): void
    {
        Gate::define('gerer-utilisateurs', fn (User $user) => $user->aLeRole(Role::Admin));
        Gate::define('voir-journal', fn (User $user) => $user->aLeRole(Role::Admin, Role::Direction));

        // Zones, villages, produits, magasins, points de collecte.
        Gate::define('gerer-referentiels', fn (User $user) => $user->aLeRole(Role::Admin, Role::Direction));
        // La direction fixe les prix (cahier §2) et les seuils : ce sont des contrôles.
        Gate::define('gerer-campagnes', fn (User $user) => $user->aLeRole(Role::Direction));
        Gate::define('gerer-parametres', fn (User $user) => $user->aLeRole(Role::Direction));

        // Données personnelles : seulement ceux qui en ont besoin (minimisation,
        // loi 2013-450). Ni l'admin (il gère des comptes) ni l'investisseur.
        Gate::define('voir-producteurs', fn (User $user) => $user->aLeRole(Role::Direction, Role::Agent, Role::Comptable, Role::Agronome));
        Gate::define('gerer-producteurs', fn (User $user) => $user->aLeRole(Role::Direction, Role::Agent));
    }

    private function journaliserLesConnexions(): void
    {
        Event::listen(function (Login $event) {
            Journal::enregistrer(ActionJournal::Connexion, $event->user instanceof User ? $event->user : null, userId: $event->user->getAuthIdentifier());
        });

        Event::listen(function (Logout $event) {
            if ($event->user !== null) {
                Journal::enregistrer(ActionJournal::Deconnexion, $event->user instanceof User ? $event->user : null, userId: $event->user->getAuthIdentifier());
            }
        });

        // L'adresse tapée est gardée : c'est elle qui dit qui a essayé.
        Event::listen(function (Failed $event) {
            Journal::enregistrer(
                ActionJournal::EchecConnexion,
                apres: ['email' => $event->credentials['email'] ?? null],
                userId: $event->user?->getAuthIdentifier(),
            );
        });

        // Le blocage (trop d'essais) est journalisé par App\Livewire\Auth\Connexion,
        // qui connaît l'adresse tapée.
    }
}
