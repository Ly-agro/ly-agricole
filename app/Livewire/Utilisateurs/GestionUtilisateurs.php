<?php

namespace App\Livewire\Utilisateurs;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Création et modification des comptes (pas d'inscription publique) : tous les rôles par
 * l'admin, les comptes d'agents seulement par la direction.
 * Chaque action revérifie le droit : une action Livewire est un point d'entrée public.
 */
#[Title('Utilisateurs')]
class GestionUtilisateurs extends Component
{
    public const MOT_DE_PASSE_MIN = 8;

    /** null = pas de formulaire ouvert ; 0 = nouveau compte ; sinon l'id modifié. */
    public ?int $editionId = null;

    public string $nom = '';

    public string $telephone = '';

    public string $email = '';

    public string $role = '';

    public string $motDePasse = '';

    public bool $actif = true;

    /** Confirmation affichée après un enregistrement (pas `$message` : @error l'écrase). */
    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('ouvrir-comptes');
    }

    /**
     * Rôles que l'utilisateur connecté peut attribuer : tous pour l'admin, « agent » seulement
     * pour la direction.
     *
     * @return list<Role>
     */
    private function rolesPermis(): array
    {
        return auth()->user()->can('gerer-utilisateurs') ? Role::cases() : [Role::Agent];
    }

    /** La direction ne voit et ne touche que les comptes d'agents. */
    private function peutGerer(User $user): bool
    {
        return in_array($user->role, $this->rolesPermis(), true);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function utilisateurs(): Collection
    {
        return User::query()
            ->when(! auth()->user()->can('gerer-utilisateurs'), fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, $this->rolesPermis())))
            ->orderByDesc('actif')->orderBy('nom')->get();
    }

    public function nouveau(): void
    {
        $this->authorize('ouvrir-comptes');

        $this->resetErrorBag();
        $this->reset('nom', 'telephone', 'email', 'role', 'motDePasse', 'actif', 'statut');
        $this->editionId = 0;
        // Un seul rôle possible (direction) : déjà choisi.
        if (count($this->rolesPermis()) === 1) {
            $this->role = $this->rolesPermis()[0]->value;
        }
    }

    public function modifier(int $id): void
    {
        $this->authorize('ouvrir-comptes');

        $user = User::findOrFail($id);
        abort_unless($this->peutGerer($user), 403);

        $this->resetErrorBag();
        $this->statut = '';
        $this->editionId = $user->id;
        $this->nom = $user->nom;
        $this->telephone = $user->telephone ?? '';
        $this->email = $user->email;
        $this->role = $user->role->value ?? '';
        $this->motDePasse = '';
        $this->actif = $user->actif;
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->editionId = null;
    }

    public function enregistrer(): void
    {
        $this->authorize('ouvrir-comptes');

        $existant = $this->editionId ? User::findOrFail($this->editionId) : null;
        abort_if($existant !== null && ! $this->peutGerer($existant), 403);

        $donnees = $this->validate([
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'telephone')->ignore($existant)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existant)],
            'role' => ['required', Rule::enum(Role::class)->only($this->rolesPermis())],
            'motDePasse' => [$existant ? 'nullable' : 'required', 'string', 'min:'.self::MOT_DE_PASSE_MIN],
            'actif' => ['boolean'],
        ], attributes: [
            'nom' => 'nom',
            'telephone' => 'téléphone',
            'email' => 'adresse e-mail',
            'role' => 'rôle',
            'motDePasse' => 'mot de passe',
        ]);

        // L'admin ne peut pas se retirer ses propres droits ni se bloquer dehors :
        // sinon plus personne ne peut gérer les comptes.
        if ($existant?->is(auth()->user())) {
            if ($donnees['role'] !== Role::Admin->value) {
                throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas changer votre propre rôle.']);
            }
            if (! $donnees['actif']) {
                throw ValidationException::withMessages(['actif' => 'Vous ne pouvez pas désactiver votre propre compte.']);
            }
        }

        $attributs = [
            'nom' => $donnees['nom'],
            'telephone' => $donnees['telephone'] ?: null,
            'email' => $donnees['email'],
            'role' => $donnees['role'],
            'actif' => $donnees['actif'],
        ];

        if (filled($donnees['motDePasse'])) {
            $attributs['password'] = $donnees['motDePasse'];
        }

        if ($existant) {
            $existant->update($attributs);
            $this->statut = "Compte de {$existant->nom} modifié.";
        } else {
            $cree = User::create($attributs);
            $this->statut = "Compte de {$cree->nom} créé.";
        }

        $this->editionId = null;
        unset($this->utilisateurs);
    }

    public function render(): View
    {
        return view('livewire.utilisateurs.gestion-utilisateurs', [
            'roles' => $this->rolesPermis(),
            'agentsSeulement' => ! auth()->user()->can('gerer-utilisateurs'),
        ]);
    }
}
