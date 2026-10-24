<?php

namespace App\Livewire\Stock;

use App\Enums\StatutLot;
use App\Exceptions\OperationRefusee;
use App\Models\Achat;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\MouvementStock;
use App\Models\User;
use App\Services\Stock;
use App\Support\Mesure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Fiche d'un lot : stock par magasin, mouvements, achats ; transfert, perte, inventaire
 * et contre-passation, via App\Services\Stock.
 */
class FicheLot extends Component
{
    #[Locked]
    public int $lotId;

    /** null | 'transfert' | 'perte' | 'inventaire' */
    public ?string $formulaire = null;

    public string $magasinId = '';

    public string $magasinDestinationId = '';

    public string $poidsKg = '';

    public string $dateMouvement = '';

    public string $motif = '';

    public ?int $aContrePasser = null;

    public string $motifContrePassation = '';

    public string $statut = '';

    public function mount(Lot $lot): void
    {
        $this->authorize('gerer-stock');
        $this->lotId = $lot->id;
    }

    public function ouvrir(string $formulaire): void
    {
        $this->authorize('gerer-stock');
        abort_unless(in_array($formulaire, ['transfert', 'perte', 'inventaire'], true), 404);
        $this->resetErrorBag();
        $this->reset('magasinId', 'magasinDestinationId', 'poidsKg', 'motif', 'statut');
        $this->dateMouvement = Carbon::today()->toDateString();
        $this->aContrePasser = null;
        $this->formulaire = $formulaire;
    }

    public function annuler(): void
    {
        $this->formulaire = null;
        $this->aContrePasser = null;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-stock');
        $this->resetErrorBag();

        $this->validate([
            'magasinId' => ['required', 'integer', Rule::exists('magasins', 'id')],
            'magasinDestinationId' => [$this->formulaire === 'transfert' ? 'required' : 'nullable', 'integer', 'different:magasinId', Rule::exists('magasins', 'id')],
            'poidsKg' => ['required', Mesure::regle(3, '250,5')],
            'dateMouvement' => ['required', 'date', 'before_or_equal:today'],
            'motif' => [$this->formulaire === 'transfert' ? 'nullable' : 'required', 'string', 'max:255'],
        ], [
            'magasinDestinationId.different' => 'Choisir un autre magasin que celui de départ.',
            'dateMouvement.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ], ['magasinId' => 'magasin', 'magasinDestinationId' => 'magasin d\'arrivée', 'poidsKg' => 'poids', 'dateMouvement' => 'date']);

        $lot = Lot::query()->findOrFail($this->lotId);
        $magasin = Magasin::query()->findOrFail((int) $this->magasinId);
        $grammes = (int) Mesure::depuisSaisie($this->poidsKg, 3);
        $date = Carbon::parse($this->dateMouvement);

        try {
            match ($this->formulaire) {
                'transfert' => Stock::transferer($lot, $magasin, Magasin::query()->findOrFail((int) $this->magasinDestinationId), $grammes, $date, $this->moi(), $this->motif ?: null),
                'perte' => Stock::perte($lot, $magasin, $grammes, $date, $this->motif, $this->moi()),
                'inventaire' => Stock::inventaire($lot, $magasin, $grammes, $date, $this->motif, $this->moi()),
                default => abort(404),
            };
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['poidsKg' => $e->getMessage()]);
        }

        $this->statut = $this->formulaire === 'inventaire' ? 'Inventaire enregistré (l\'écart, s\'il y en a un, est un mouvement).' : 'Mouvement enregistré.';
        $this->formulaire = null;
    }

    public function basculerStatut(): void
    {
        $this->authorize('gerer-stock');
        $lot = Lot::query()->findOrFail($this->lotId);
        $lot->update(['statut' => $lot->statut === StatutLot::Ouvert ? StatutLot::Ferme : StatutLot::Ouvert]);
        $this->statut = 'Lot '.($lot->statut === StatutLot::Ouvert ? 'rouvert.' : 'fermé : il ne reçoit plus d\'achats.');
    }

    public function preparerContrePassation(int $id): void
    {
        $this->authorize('gerer-stock');
        $this->resetErrorBag();
        $this->formulaire = null;
        $this->motifContrePassation = '';
        $this->aContrePasser = MouvementStock::query()->where('lot_id', $this->lotId)->findOrFail($id)->id;
    }

    public function contrePasser(): void
    {
        $this->authorize('gerer-stock');
        $this->resetErrorBag();

        try {
            Stock::contrePasser(MouvementStock::query()->where('lot_id', $this->lotId)->findOrFail((int) $this->aContrePasser), $this->motifContrePassation, $this->moi());
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motifContrePassation' => $e->getMessage()]);
        }

        $this->aContrePasser = null;
        $this->statut = 'Mouvement contre-passé.';
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $lot = Lot::query()->with('produit', 'campagne', 'magasin')->findOrFail($this->lotId);
        $mouvements = MouvementStock::query()->with('magasin', 'auteur', 'contrePassation')->where('lot_id', $lot->id)->orderByDesc('id')->get();
        $magasins = Magasin::query()->orderBy('nom')->get();
        $parMagasin = $mouvements->groupBy('magasin_id')->map(fn ($m) => (int) $m->sum('grammes'))->filter(fn ($g) => $g !== 0);

        return view('livewire.stock.fiche-lot', [
            'lot' => $lot,
            'mouvements' => $mouvements,
            'parMagasin' => $parMagasin,
            'total' => (int) $parMagasin->sum(),
            'magasins' => $magasins,
            'achats' => Achat::query()->with('producteur', 'pisteur')->where('lot_id', $lot->id)->orderByDesc('date_achat')->limit(50)->get(),
        ])->title('Lot '.$lot->code);
    }
}
