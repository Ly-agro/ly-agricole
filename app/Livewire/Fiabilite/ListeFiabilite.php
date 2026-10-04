<?php

namespace App\Livewire\Fiabilite;

use App\Enums\StatutPret;
use App\Models\Pret;
use App\Models\Producteur;
use App\Services\FiabiliteProducteur;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Producteurs qui ont reçu au moins un prêt : historique de remboursement et plafond proposé. Lecture seule. */
#[Title('Fiabilité des producteurs')]
class ListeFiabilite extends Component
{
    use WithPagination;

    public const PAR_PAGE = 20;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public function mount(): void
    {
        $this->authorize('voir-fiabilite');
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $recherche = trim($this->recherche);

        $producteurs = Producteur::query()
            ->whereIn('id', Pret::query()->whereIn('statut', [StatutPret::Decaisse, StatutPret::Solde])->select('producteur_id'))
            ->when($recherche !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('nom', 'like', '%'.$recherche.'%')->orWhere('prenoms', 'like', '%'.$recherche.'%')->orWhere('code', 'like', '%'.$recherche.'%')))
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(self::PAR_PAGE);

        return view('livewire.fiabilite.liste-fiabilite', [
            'producteurs' => $producteurs,
            'fiches' => $producteurs->getCollection()->mapWithKeys(fn (Producteur $p) => [$p->id => FiabiliteProducteur::fiche($p)]),
        ]);
    }
}
