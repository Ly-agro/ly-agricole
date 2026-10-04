<?php

namespace App\Livewire\Fiabilite;

use App\Models\GroupeProducteur;
use App\Services\CautionSolidaire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Situation d'un groupe (membres, prêts, retards) avec le détail nominatif. Lecture seule. */
#[Title('Groupe de producteurs')]
class SituationGroupe extends Component
{
    public GroupeProducteur $groupe;

    public function mount(GroupeProducteur $groupe): void
    {
        $this->authorize('voir-fiabilite');
        $this->groupe = $groupe->load('village');
    }

    public function render(): View
    {
        return view('livewire.fiabilite.situation-groupe', ['situation' => CautionSolidaire::situation($this->groupe)]);
    }
}
