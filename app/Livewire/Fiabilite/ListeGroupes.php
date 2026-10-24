<?php

namespace App\Livewire\Fiabilite;

use App\Models\GroupeProducteur;
use App\Services\CautionSolidaire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Groupes de producteurs : exposition et retards, pour la direction et la comptabilité. Lecture seule. */
#[Title('Groupes de producteurs')]
class ListeGroupes extends Component
{
    use WithPagination;

    public const PAR_PAGE = 15;

    public function mount(): void
    {
        $this->authorize('voir-fiabilite');
    }

    public function render(): View
    {
        $groupes = GroupeProducteur::query()->with('village')->withCount('membres')->orderBy('nom')->paginate(self::PAR_PAGE);

        return view('livewire.fiabilite.liste-groupes', [
            'groupes' => $groupes,
            'situations' => $groupes->getCollection()->mapWithKeys(fn (GroupeProducteur $g) => [$g->id => CautionSolidaire::situation($g)]),
        ]);
    }
}
