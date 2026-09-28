<?php

namespace App\Livewire\Prets;

use App\Enums\StatutPret;
use App\Models\Campagne;
use App\Models\Decaissement;
use App\Models\Pret;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portefeuille de prêts : totaux (demandé, validé, décaissé, kilos attendus) et liste.
 * Les totaux sont des sommes calculées à chaque affichage, jamais des colonnes.
 */
#[Title('Prêts')]
class ListePrets extends Component
{
    use WithPagination;

    public const PAR_PAGE = 30;

    #[Url(as: 'campagne', except: '')]
    public string $filtreCampagne = '';

    #[Url(as: 'statut', except: '')]
    public string $filtreStatut = '';

    public function mount(): void
    {
        $this->authorize('voir-prets');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** @return Builder<Pret> */
    private function requete(): Builder
    {
        return Pret::query()
            ->when(ctype_digit($this->filtreCampagne), fn ($q) => $q->where('campagne_id', (int) $this->filtreCampagne))
            ->when(StatutPret::tryFrom($this->filtreStatut), fn ($q, $s) => $q->where('statut', $s));
    }

    public function render(): View
    {
        $this->authorize('voir-prets');

        $accordes = $this->requete()->whereIn('statut', [StatutPret::Valide, StatutPret::Decaisse]);
        $decaisse = (int) Decaissement::query()
            ->whereIn('pret_id', (clone $accordes)->select('id'))
            ->whereDoesntHave('mouvement.contrePassation')
            ->sum('montant_fcfa');

        return view('livewire.prets.liste-prets', [
            'prets' => $this->requete()->with('producteur.village', 'campagne.produit', 'validations')
                ->orderByRaw('CASE WHEN statut = ? THEN 0 ELSE 1 END', [StatutPret::Demande->value])
                ->orderByDesc('created_at')->paginate(self::PAR_PAGE),
            'totaux' => [
                'demandes' => $this->requete()->where('statut', StatutPret::Demande)->count(),
                'montantDemande' => (int) $this->requete()->where('statut', StatutPret::Demande)->sum('montant_fcfa'),
                'accordes' => (clone $accordes)->count(),
                'montantAccorde' => (int) (clone $accordes)->sum('montant_fcfa'),
                'decaisse' => $decaisse,
                'grammesAttendus' => (int) (clone $accordes)->sum('grammes_attendus'),
            ],
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
            'statuts' => StatutPret::cases(),
        ]);
    }
}
