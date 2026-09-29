<?php

namespace App\Livewire\Stock;

use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Models\Campagne;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\MouvementStock;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lots et leur stock (somme des mouvements, une requête groupée). Un lot se crée pour
 * une campagne et un magasin ; il reçoit les achats tant qu'il est ouvert.
 */
#[Title('Lots')]
class ListeLots extends Component
{
    public bool $formulaireOuvert = false;

    public string $campagneId = '';

    public string $magasinId = '';

    public string $description = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('gerer-stock');
    }

    public function ouvrir(): void
    {
        $this->authorize('gerer-stock');
        $this->resetErrorBag();
        $this->reset('magasinId', 'description', 'statut');
        $this->campagneId = (string) (Campagne::query()->where('statut', StatutCampagne::Ouverte)->value('id') ?? '');
        $this->formulaireOuvert = true;
    }

    public function creer(): void
    {
        $this->authorize('gerer-stock');

        $this->validate([
            'campagneId' => ['required', 'integer', Rule::exists('campagnes', 'id')],
            'magasinId' => ['required', 'integer', Rule::exists('magasins', 'id')->where('actif', true)],
            'description' => ['nullable', 'string', 'max:255'],
        ], attributes: ['campagneId' => 'campagne', 'magasinId' => 'magasin']);

        $campagne = Campagne::query()->findOrFail((int) $this->campagneId);
        $lot = Lot::query()->create([
            'produit_id' => $campagne->produit_id,
            'campagne_id' => $campagne->id,
            'magasin_id' => (int) $this->magasinId,
            'statut' => StatutLot::Ouvert,
            'description' => trim($this->description) ?: null,
            'cree_par' => auth()->id(),
        ]);

        $this->formulaireOuvert = false;
        $this->statut = "Lot {$lot->code} créé.";
    }

    public function render(): View
    {
        $stocks = MouvementStock::query()
            ->selectRaw('lot_id, magasin_id, SUM(grammes) AS grammes')
            ->groupBy('lot_id', 'magasin_id')
            ->get()
            ->groupBy('lot_id')
            ->map(fn ($lignes) => $lignes->pluck('grammes', 'magasin_id')->map(fn ($g) => (int) $g));

        return view('livewire.stock.liste-lots', [
            'lots' => Lot::query()->with('produit', 'campagne', 'magasin')->orderByDesc('id')->get(),
            'stocks' => $stocks,
            'magasins' => Magasin::query()->orderBy('nom')->get(),
            'campagnes' => Campagne::query()->with('produit')->where('statut', '!=', StatutCampagne::Cloturee)->orderByDesc('debut')->get(),
        ]);
    }
}
