<?php

namespace App\Livewire\Appareils;

use App\Exceptions\OperationRefusee;
use App\Models\User;
use App\Services\Appareils;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Téléphones connectés à l'appli terrain : qui, depuis quand, dernière synchronisation, et de quoi
 * COUPER un téléphone perdu (question 25). Direction et administrateur.
 */
#[Title('Appareils')]
class ListeAppareils extends Component
{
    /** Jeton en cours de révocation (formulaire du motif), ou null. */
    public ?int $aCouper = null;

    /** Utilisateur dont on coupe TOUS les appareils, ou null. */
    public ?int $aCouperTous = null;

    public string $motif = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('gerer-appareils');
    }

    public function demanderCoupure(int $jetonId): void
    {
        $this->authorize('gerer-appareils');
        $this->reset('aCouperTous', 'motif');
        $this->resetErrorBag();
        $this->aCouper = $jetonId;
    }

    public function demanderCoupureTous(int $utilisateurId): void
    {
        $this->authorize('gerer-appareils');
        $this->reset('aCouper', 'motif');
        $this->resetErrorBag();
        $this->aCouperTous = $utilisateurId;
    }

    public function annuler(): void
    {
        $this->reset('aCouper', 'aCouperTous', 'motif');
        $this->resetErrorBag();
    }

    public function couper(): void
    {
        $this->authorize('gerer-appareils');
        $this->resetErrorBag();

        try {
            if ($this->aCouper !== null) {
                $jeton = PersonalAccessToken::query()->findOrFail($this->aCouper);
                Appareils::revoquer($this->moi(), $jeton, $this->motif);
                $this->statut = 'Appareil coupé : il ne peut plus rien envoyer et devra se reconnecter.';
            } elseif ($this->aCouperTous !== null) {
                $nb = Appareils::revoquerTous($this->moi(), User::query()->findOrFail($this->aCouperTous), $this->motif);
                $this->statut = $nb.' appareil(s) coupé(s).';
            } else {
                return;
            }
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motif' => $e->getMessage()]);
        }

        $this->reset('aCouper', 'aCouperTous', 'motif');
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.appareils.liste-appareils', ['appareils' => Appareils::lister()]);
    }
}
