<?php

namespace App\Livewire\Rendements;

use App\Models\Campagne;
use App\Services\Geo\CarteSvg;
use App\Services\Indicateurs;
use App\Services\Rendements;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Classement des producteurs par rendement (kg/ha) sur une campagne — lecture seule. */
#[Title('Rendements')]
class ClassementRendements extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $campagneId = '';

    public function mount(): void
    {
        $this->authorize('voir-rendements');
    }

    public function render(): View
    {
        $indicateurs = new Indicateurs;
        $campagnes = $indicateurs->campagnes();
        $campagne = $campagnes->firstWhere('id', (int) $this->campagneId) ?? $indicateurs->campagneParDefaut($campagnes);

        $carte = $campagne instanceof Campagne ? Rendements::carte($campagne) : null;
        $dessin = $carte === null ? null : CarteSvg::projeter(array_column($carte['parcelles'], 'geometrie', 'id'));

        return view('livewire.rendements.classement-rendements', [
            'carte' => $carte,
            'dessin' => $dessin,
            'campagnes' => $campagnes,
            'campagne' => $campagne,
            'resultat' => $campagne instanceof Campagne ? Rendements::classement($campagne) : null,
        ]);
    }
}
