<?php

namespace App\Livewire\Visites;

use App\Enums\PratiqueCulturale;
use App\Models\Visite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Visites de parcelle reçues du terrain, les plus récentes d'abord : lecture seule (la
 * fiche se saisit sur le téléphone). Filtres : producteur ou parcelle, pratique.
 */
#[Title('Visites')]
class ListeVisites extends Component
{
    use WithPagination;

    public const PAR_PAGE = 30;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(as: 'pratique', except: '')]
    public string $pratique = '';

    public function mount(): void
    {
        $this->authorize('voir-visites');
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function updatedPratique(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $terme = trim($this->recherche);
        $pratique = PratiqueCulturale::tryFrom($this->pratique);

        return view('livewire.visites.liste-visites', [
            'visites' => Visite::query()
                ->with('parcelle.producteur.village', 'parcelle.produit', 'auteur', 'photos')
                ->when($terme !== '', fn ($q) => $q->whereHas('parcelle', fn ($p) => $p
                    ->where('nom', 'like', "%{$terme}%")
                    ->orWhereHas('producteur', fn ($pr) => $pr->where('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%")->orWhere('code', 'like', "%{$terme}%"))))
                // JSON : chercher le code entre guillemets suffit (codes sans guillemets).
                ->when($pratique, fn ($q, PratiqueCulturale $p) => $q->where('pratiques', 'like', '%"'.$p->value.'"%'))
                ->orderByDesc('date_visite')->orderByDesc('created_at')
                ->paginate(self::PAR_PAGE),
            'pratiques' => PratiqueCulturale::cases(),
        ]);
    }
}
