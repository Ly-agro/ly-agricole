<?php

namespace App\Livewire\Rendements;

use App\Models\Producteur;
use App\Services\Rendements;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Rendement d'un producteur campagne après campagne — lecture seule. */
#[Title('Évolution du rendement')]
class EvolutionProducteur extends Component
{
    public Producteur $producteur;

    public function mount(Producteur $producteur): void
    {
        $this->authorize('voir-rendements');
        $this->producteur = $producteur;
    }

    public function render(): View
    {
        return view('livewire.rendements.evolution-producteur', [
            'lignes' => Rendements::evolution($this->producteur),
        ]);
    }
}
