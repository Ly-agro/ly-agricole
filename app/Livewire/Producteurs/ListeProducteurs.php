<?php

namespace App\Livewire\Producteurs;

use App\Models\Producteur;
use App\Models\Village;
use App\Support\Telephone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Producteurs')]
class ListeProducteurs extends Component
{
    use WithPagination;

    public const PAR_PAGE = 25;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'village', except: '')]
    public string $filtreVillage = '';

    public function mount(): void
    {
        $this->authorize('voir-producteurs');
    }

    public function updated(string $propriete): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('voir-producteurs');

        $terme = trim($this->recherche);
        $telephone = Telephone::normaliser($terme);

        $producteurs = Producteur::query()
            ->with('village.zone', 'groupe')
            ->when($terme !== '', function ($q) use ($terme, $telephone) {
                $q->where(function ($q) use ($terme, $telephone) {
                    $q->where('code', mb_strtoupper($terme))
                        ->orWhere('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%")
                        ->orWhere('telephone', $telephone)
                        ->orWhere('numero_mobile_money', $telephone);
                });
            })
            ->when(ctype_digit($this->filtreVillage), fn ($q) => $q->where('village_id', (int) $this->filtreVillage))
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(self::PAR_PAGE);

        return view('livewire.producteurs.liste-producteurs', [
            'producteurs' => $producteurs,
            'villages' => Village::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }
}
