<?php

namespace App\Providers;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Models\Achat;
use App\Models\Apport;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Decaissement;
use App\Models\Depense;
use App\Models\Encaissement;
use App\Models\GroupeProducteur;
use App\Models\Intrant;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\MouvementIntrant;
use App\Models\MouvementStock;
use App\Models\MouvementTresorerie;
use App\Models\Parametre;
use App\Models\Parcelle;
use App\Models\Pisteur;
use App\Models\PointCollecte;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\Produit;
use App\Models\Remboursement;
use App\Models\User;
use App\Models\ValidationPret;
use App\Models\Vente;
use App\Models\Village;
use App\Models\Zone;
use App\Services\Journal;
use App\Services\Sms\EnvoyeurSms;
use App\Services\Sms\EnvoyeurSmsJournal;
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
        // D10 : un seul pilote pour l'instant ; un fournisseur réel s'ajoutera ici.
        $this->app->bind(EnvoyeurSms::class, fn () => match (config('services.sms.pilote')) {
            'journal' => new EnvoyeurSmsJournal,
            default => throw new \InvalidArgumentException('Pilote SMS inconnu : '.config('services.sms.pilote')),
        });
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
            'compte_tresorerie' => CompteTresorerie::class,
            'mouvement_tresorerie' => MouvementTresorerie::class,
            'categorie_depense' => CategorieDepense::class,
            'depense' => Depense::class,
            'pret' => Pret::class,
            'validation_pret' => ValidationPret::class,
            'decaissement' => Decaissement::class,
            'intrant' => Intrant::class,
            'mouvement_intrant' => MouvementIntrant::class,
            'pisteur' => Pisteur::class,
            'lot' => Lot::class,
            'achat' => Achat::class,
            'mouvement_stock' => MouvementStock::class,
            'remboursement' => Remboursement::class,
            'vente' => Vente::class,
            'encaissement' => Encaissement::class,
            'apport' => Apport::class,
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

        // Trésorerie : comptes, entrées, virements, avances, contre-passations, catégories.
        Gate::define('gerer-tresorerie', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        // Un agent saisit ses dépenses de terrain (depuis sa caisse) ; il ne valide pas.
        Gate::define('saisir-depenses', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable, Role::Agent));
        Gate::define('valider-depenses', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));

        // Prêts (cahier §2) : l'agent monte la demande après la visite, la direction
        // valide, la comptabilité décaisse.
        Gate::define('voir-prets', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable, Role::Agent));
        Gate::define('saisir-prets', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable, Role::Agent));
        Gate::define('valider-prets', fn (User $user) => $user->aLeRole(Role::Direction));
        Gate::define('decaisser-prets', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));

        // Stock d'intrants : fiches, entrées, pertes, ajustements, contre-passations.
        // Les distributions à crédit suivent le droit de décaisser un prêt.
        Gate::define('gerer-intrants', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));

        // Achats bord-champ : l'agent pèse et paie depuis sa caisse ; au-dessus du
        // seuil, une autre personne valide. Lots et stock : direction et comptable.
        Gate::define('saisir-achats', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable, Role::Agent));
        Gate::define('valider-achats', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        Gate::define('gerer-stock', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        // Remboursement d'un prêt en espèces : encaissé par la comptabilité.
        Gate::define('encaisser-remboursements', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));

        // Reventes (cahier §7) : négociées au bureau, pas sur le terrain — contrairement
        // aux achats, pas de droit agent ici. Encaissement : même droit que la trésorerie.
        Gate::define('voir-ventes', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        Gate::define('saisir-ventes', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        Gate::define('valider-ventes', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        Gate::define('encaisser-ventes', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));

        // Apports de campagne (contrat art. 5, 9) et portail en lecture seule de
        // l'investisseur (cahier §2 : « consulte sa quote-part », en attendant le
        // calcul exact — voir App\Services\Apports).
        Gate::define('gerer-apports', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        // Rendements (cahier §4) : lecture des chiffres de tous les producteurs.
        Gate::define('voir-rendements', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        // Résultat net et partage (contrat art. 10 à 14) : direction et comptabilité, lecture.
        Gate::define('voir-resultat-campagne', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        // Rapports du contrat (art. 18), distincts de `voir-rapports` (rapports de gestion, semaine 10).
        Gate::define('voir-rapport-campagne', fn (User $user) => $user->aLeRole(Role::Direction, Role::Comptable));
        Gate::define('voir-portail-investisseur', fn (User $user) => $user->aLeRole(Role::Investisseur));
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
