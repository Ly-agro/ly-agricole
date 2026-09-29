<?php

namespace App\Livewire\Ia;

use App\Enums\StatutDiagnostic;
use App\Exceptions\OperationRefusee;
use App\Models\Diagnostic;
use App\Models\User;
use App\Services\Ia\Diagnostics;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Diagnostics des photos de visite : l'agronome confirme ou corrige, puis demande un
 * brouillon de conseil. Rien de cet écran n'est envoyé à un producteur.
 */
#[Title('Diagnostics IA')]
class ListeDiagnostics extends Component
{
    use WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $filtre = '';

    public ?int $aValider = null;

    public string $classe = '';

    public string $note = '';

    public ?int $pourConseil = null;

    public string $observation = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('voir-ia');
    }

    public function preparerValidation(int $id): void
    {
        $this->authorize('valider-diagnostics');
        $this->resetErrorBag();
        $d = Diagnostic::query()->findOrFail($id);
        $this->aValider = $d->id;
        $this->classe = (string) ($d->classe_retenue ?? $d->classe_proposee);
        $this->note = (string) $d->note_agronome;
    }

    public function valider(): void
    {
        $this->authorize('valider-diagnostics');
        try {
            $d = Diagnostics::valider(Diagnostic::query()->findOrFail((int) $this->aValider), $this->moi(), $this->classe, $this->note);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['classe' => $e->getMessage()]);
        }
        $this->aValider = null;
        $this->statut = 'Diagnostic '.mb_strtolower($d->statut->libelle()).'.';
    }

    public function preparerConseil(int $id): void
    {
        $this->authorize('valider-diagnostics');
        $this->resetErrorBag();
        $this->pourConseil = Diagnostic::query()->findOrFail($id)->id;
        $this->observation = '';
    }

    public function demanderConseil(): void
    {
        $this->authorize('valider-diagnostics');
        try {
            Diagnostics::demanderConseil(Diagnostic::query()->findOrFail((int) $this->pourConseil), $this->moi(), $this->observation);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['observation' => $e->getMessage()]);
        }
        $this->pourConseil = null;
        $this->statut = 'Brouillon demandé au service IA : il apparaîtra ici une fois rédigé et contrôlé.';
    }

    private function moi(): User
    {
        /** @var User $u */
        $u = auth()->user();

        return $u;
    }

    public function render(): View
    {
        return view('livewire.ia.liste-diagnostics', [
            'diagnostics' => Diagnostic::query()->with('photo', 'visite.parcelle.producteur', 'visite.parcelle.produit', 'validateur', 'demandeur')
                ->when(StatutDiagnostic::tryFrom($this->filtre), fn ($q, StatutDiagnostic $s) => $q->where('statut', $s))
                ->latest()->paginate(20),
            'statuts' => StatutDiagnostic::cases(),
            'peutValider' => $this->moi()->can('valider-diagnostics'),
            'serviceConfigure' => is_string(config('ia.url')),
        ]);
    }
}
