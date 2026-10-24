<?php

namespace App\Livewire\RapportCampagne;

use App\Models\Campagne;
use App\Services\RapportCampagne;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Point d'étape (contrat de campagne, art. 18.1) : aperçu des chiffres relus dans les
 * registres, texte libre des principaux événements, puis PDF à adresser aux investisseurs.
 */
#[Title('Rapport de campagne')]
class PointEtape extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $campagneId = '';

    public string $evenements = '';

    public function mount(): void
    {
        $this->authorize('voir-rapport-campagne');
        if ($this->campagneId === '') {
            $this->campagneId = (string) (Campagne::query()->orderByDesc('debut')->value('id') ?? '');
        }
    }

    public function render(): View
    {
        $campagne = Campagne::query()->with('produit')->find((int) $this->campagneId);

        return view('livewire.rapport-campagne.point-etape', [
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
            'campagne' => $campagne,
            'rapport' => $campagne === null ? null : RapportCampagne::pointEtape($campagne),
            'maxEvenements' => RapportCampagne::MAX_EVENEMENTS,
        ]);
    }
}
