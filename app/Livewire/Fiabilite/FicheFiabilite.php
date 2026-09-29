<?php

namespace App\Livewire\Fiabilite;

use App\Models\Producteur;
use App\Services\FiabiliteProducteur;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Historique de remboursement d'un producteur et plafond proposé. La direction décide ailleurs. */
#[Title('Fiabilité du producteur')]
class FicheFiabilite extends Component
{
    public Producteur $producteur;

    public function mount(Producteur $producteur): void
    {
        $this->authorize('voir-fiabilite');
        $this->producteur = $producteur;
    }

    public function render(): View
    {
        return view('livewire.fiabilite.fiche-fiabilite', ['fiche' => FiabiliteProducteur::fiche($this->producteur)]);
    }
}
