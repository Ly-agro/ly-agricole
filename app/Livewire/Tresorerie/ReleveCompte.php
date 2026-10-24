<?php

namespace App\Livewire\Tresorerie;

use App\Exceptions\OperationRefusee;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Models\User;
use App\Services\Tresorerie;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Relevé d'un compte : chaque mouvement avec le solde après lui. Rien ne se modifie ;
 * une erreur se corrige par contre-passation motivée.
 */
class ReleveCompte extends Component
{
    #[Locked]
    public int $compteId;

    /** Mouvement dont la contre-passation est en préparation. */
    public ?int $aContrePasser = null;

    /** Motif de la contre-passation (pas `$message` : @error l'écrase). */
    public string $motif = '';

    public string $statut = '';

    public function mount(CompteTresorerie $compte): void
    {
        $this->authorize('gerer-tresorerie');
        $this->compteId = $compte->id;
    }

    public function preparerContrePassation(int $mouvementId): void
    {
        $this->authorize('gerer-tresorerie');
        $this->resetErrorBag();
        $this->statut = '';
        $this->motif = '';
        $this->aContrePasser = MouvementTresorerie::query()->where('compte_id', $this->compteId)->findOrFail($mouvementId)->id;
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->aContrePasser = null;
    }

    public function contrePasser(): void
    {
        $this->authorize('gerer-tresorerie');
        // Sans cela, le message d'un essai refusé reste affiché après un essai réussi.
        $this->resetErrorBag();

        $mouvement = MouvementTresorerie::query()->where('compte_id', $this->compteId)->findOrFail((int) $this->aContrePasser);
        /** @var User $auteur */
        $auteur = auth()->user();

        try {
            $crees = Tresorerie::contrePasser($mouvement, $this->motif, $auteur);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motif' => $e->getMessage()]);
        }

        $this->statut = count($crees) > 1
            ? 'Virement contre-passé sur ses deux comptes.'
            : 'Mouvement contre-passé.';
        $this->aContrePasser = null;
    }

    public function render(): View
    {
        $compte = CompteTresorerie::query()->findOrFail($this->compteId);
        $mouvements = MouvementTresorerie::query()
            ->with('auteur', 'contrePassation')
            ->where('compte_id', $compte->id)
            ->orderBy('date_operation')->orderBy('id')
            ->get();

        // Solde courant, calculé ici en entiers, dans l'ordre des opérations.
        $cumul = 0;
        $soldesApres = [];
        foreach ($mouvements as $m) {
            $cumul += $m->montantSigne();
            $soldesApres[$m->id] = $cumul;
        }

        return view('livewire.tresorerie.releve-compte', [
            'compte' => $compte,
            'mouvements' => $mouvements->reverse(),
            'soldesApres' => $soldesApres,
            'solde' => $compte->solde(),
        ])->title('Relevé — '.$compte->nom);
    }
}
