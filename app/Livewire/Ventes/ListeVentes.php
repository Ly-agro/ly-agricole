<?php

namespace App\Livewire\Ventes;

use App\Enums\StatutVente;
use App\Exceptions\OperationRefusee;
use App\Models\User;
use App\Models\Vente;
use App\Services\Ventes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reventes : direction et comptabilité voient tout et valident celles de l'autre (le
 * service revérifie la séparation des tâches).
 */
#[Title('Ventes')]
class ListeVentes extends Component
{
    use WithPagination;

    public const PAR_PAGE = 30;

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    public ?string $aRefuser = null;

    public string $motifRefus = '';

    public string $statut = '';

    public function mount(): void
    {
        abort_unless(Gate::any(['saisir-ventes', 'valider-ventes']), 403);
        $this->statut = (string) session('statut', '');
    }

    public function updatedFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function valider(string $id): void
    {
        $this->authorize('valider-ventes');
        $this->resetErrorBag();

        try {
            Ventes::valider(Vente::query()->findOrFail($id), $this->moi());
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }

        $this->statut = 'Vente validée : le stock du lot est sorti.';
    }

    public function preparerRefus(string $id): void
    {
        $this->authorize('valider-ventes');
        $this->resetErrorBag();
        $this->motifRefus = '';
        $this->aRefuser = Vente::query()->findOrFail($id)->id;
    }

    public function refuser(): void
    {
        $this->authorize('valider-ventes');
        $this->resetErrorBag();

        try {
            Ventes::refuser(Vente::query()->findOrFail((string) $this->aRefuser), $this->moi(), $this->motifRefus);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motifRefus' => $e->getMessage()]);
        }

        $this->aRefuser = null;
        $this->statut = 'Vente refusée.';
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $moi = $this->moi();
        $toutVoir = $moi->can('valider-ventes');

        return view('livewire.ventes.liste-ventes', [
            'ventes' => Vente::query()
                ->with('lot', 'campagne.produit', 'auteur', 'validateur')
                ->when(! $toutVoir, fn ($q) => $q->where('cree_par', $moi->id))
                ->when(StatutVente::tryFrom($this->filtreStatut), fn ($q, $s) => $q->where('statut', $s))
                ->orderByRaw('CASE WHEN statut = ? THEN 0 ELSE 1 END', [StatutVente::AValider->value])
                ->orderByDesc('date_vente')->paginate(self::PAR_PAGE),
            'statuts' => StatutVente::cases(),
            'peutValider' => $toutVoir,
            'moi' => $moi->id,
        ]);
    }
}
