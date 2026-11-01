<?php

namespace App\Livewire\Referentiels;

use App\Enums\CleParametre;
use App\Exceptions\OperationRefusee;
use App\Models\CompteTresorerie;
use App\Models\Parametre;
use App\Models\User;
use App\Services\GestionComptesTresorerie;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Paramètres (seuils de validation…), réservés à la direction. La liste des clés vient
 * du code (CleParametre) ; aucune valeur par défaut : « non défini » reste visible.
 */
class Parametres extends Component
{
    /** Clé en cours de modification, ou null. */
    public ?string $edition = null;

    public string $valeur = '';

    public string $statut = '';

    public ?int $compteActionId = null;

    public ?string $actionCompte = null;

    #[Locked]
    public string $routePage = '';

    public function mount(): void
    {
        $this->authorize('gerer-parametres');
        $this->routePage = (string) request()->route()?->getName();
    }

    public function modifier(string $cle): void
    {
        $this->authorize('gerer-parametres');

        $cleParametre = CleParametre::from($cle);
        $this->resetErrorBag();
        $this->statut = '';
        $this->edition = $cleParametre->value;
        $this->valeur = $cleParametre->estUnChoix()
            ? (string) Parametre::query()->where('cle', $cleParametre)->value('valeur')
            : (string) Parametre::entier($cleParametre);
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->edition = null;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-parametres');

        $this->validate(['edition' => ['required', Rule::enum(CleParametre::class)]]);
        $cle = CleParametre::from((string) $this->edition);

        $this->validate([
            // Montants en FCFA entiers (D4), ou une des valeurs prévues. Vide = non défini.
            'valeur' => $cle->estUnChoix()
                ? ['nullable', Rule::in(array_keys($cle->options()))]
                : ['nullable', 'integer', 'min:0', 'max:99999999999'],
        ], attributes: ['valeur' => 'valeur']);

        $valeur = $this->valeur === '' ? null : ($cle->estUnChoix() ? $this->valeur : (string) (int) $this->valeur);

        $parametre = Parametre::query()->firstOrNew(['cle' => $cle]);
        $parametre->valeur = $valeur;
        $parametre->save();

        $this->statut = $cle->libelle().' : '.($valeur === null ? 'non défini.' : 'enregistré.');
        $this->edition = null;
    }

    public function preparerActionCompte(int $id, string $action): void
    {
        $this->authorize('gerer-parametres');
        abort_unless(in_array($action, ['fermer', 'supprimer'], true), 404);
        $this->resetErrorBag();

        $compte = CompteTresorerie::query()->findOrFail($id);
        if ($compte->solde() !== 0) {
            throw ValidationException::withMessages(['actionCompte' => 'Seul un compte dont le solde est nul peut être fermé ou supprimé.']);
        }
        if ($action === 'supprimer' && ! GestionComptesTresorerie::peutSupprimer($compte)) {
            throw ValidationException::withMessages(['actionCompte' => 'Ce compte a un historique : fermez-le pour le conserver.']);
        }

        $this->compteActionId = $id;
        $this->actionCompte = $action;
    }

    public function confirmerActionCompte(): void
    {
        $this->authorize('gerer-parametres');
        $this->resetErrorBag();

        if ($this->compteActionId === null || ! in_array($this->actionCompte, ['fermer', 'supprimer'], true)) {
            throw ValidationException::withMessages(['actionCompte' => 'Choisissez une action sur un compte.']);
        }

        /** @var User $auteur */
        $auteur = auth()->user();
        $compte = CompteTresorerie::query()->findOrFail($this->compteActionId);

        try {
            if ($this->actionCompte === 'fermer') {
                $compte = GestionComptesTresorerie::fermer($compte, $auteur);
                $this->statut = "Compte « {$compte->nom} » fermé ; son historique est conservé.";
            } else {
                $nom = $compte->nom;
                GestionComptesTresorerie::supprimer($compte, $auteur);
                $this->statut = "Compte « {$nom} » supprimé.";
            }
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['actionCompte' => $e->getMessage()]);
        }

        $this->compteActionId = null;
        $this->actionCompte = null;
    }

    public function annulerActionCompte(): void
    {
        $this->resetErrorBag();
        $this->compteActionId = null;
        $this->actionCompte = null;
    }

    public function render(): View
    {
        $valeurs = Parametre::query()->pluck('valeur', 'cle')->all();
        $comptes = CompteTresorerie::query()->with('campagne.produit')->orderByDesc('actif')->orderBy('nom')->get();
        $soldesComptes = $comptes->mapWithKeys(fn (CompteTresorerie $compte) => [$compte->id => $compte->solde()]);
        $comptesSupprimables = $comptes
            ->filter(fn (CompteTresorerie $compte) => GestionComptesTresorerie::peutSupprimer($compte))
            ->modelKeys();

        return view('livewire.referentiels.parametres', [
            'cles' => CleParametre::cases(),
            'valeurs' => $valeurs,
            'comptesTresorerie' => $comptes,
            'soldesComptes' => $soldesComptes,
            'comptesSupprimables' => $comptesSupprimables,
        ])->title('Paramètres');
    }
}
