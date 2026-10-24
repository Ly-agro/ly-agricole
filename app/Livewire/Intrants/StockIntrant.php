<?php

namespace App\Livewire\Intrants;

use App\Enums\TypeMouvementIntrant;
use App\Exceptions\OperationRefusee;
use App\Models\Intrant;
use App\Models\Magasin;
use App\Models\MouvementIntrant;
use App\Models\User;
use App\Services\StockIntrants;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Stock d'intrants par magasin (somme des mouvements) : entrées, pertes, ajustements
 * et historique. Les remises à crédit se font depuis la fiche du prêt.
 */
#[Title('Stock d\'intrants')]
class StockIntrant extends Component
{
    use WithPagination;

    /** null | 'entree' | 'perte' | 'ajustement' */
    public ?string $formulaire = null;

    public string $intrantId = '';

    public string $magasinId = '';

    public string $quantite = '';

    public string $dateMouvement = '';

    public string $motif = '';

    public ?int $aContrePasser = null;

    public string $motifContrePassation = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('gerer-intrants');
    }

    public function ouvrir(string $formulaire): void
    {
        $this->authorize('gerer-intrants');
        abort_unless(in_array($formulaire, ['entree', 'perte', 'ajustement'], true), 404);
        $this->resetErrorBag();
        $this->reset('intrantId', 'magasinId', 'quantite', 'motif', 'statut');
        $this->dateMouvement = Carbon::today()->toDateString();
        $this->formulaire = $formulaire;
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->formulaire = null;
        $this->aContrePasser = null;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-intrants');
        $this->resetErrorBag();

        $this->validate([
            'intrantId' => ['required', 'integer', Rule::exists('intrants', 'id')],
            'magasinId' => ['required', 'integer', Rule::exists('magasins', 'id')],
            'quantite' => ['required', 'integer', $this->formulaire === 'ajustement' ? 'not_in:0' : 'min:1'],
            'dateMouvement' => ['required', 'date', 'before_or_equal:today'],
            'motif' => [$this->formulaire === 'entree' ? 'nullable' : 'required', 'string', 'max:255'],
        ], [
            'dateMouvement.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'quantite.not_in' => 'Un ajustement est une quantité positive ou négative, pas zéro.',
        ], ['intrantId' => 'intrant', 'magasinId' => 'magasin', 'dateMouvement' => 'date']);

        $intrant = Intrant::query()->findOrFail((int) $this->intrantId);
        $magasin = Magasin::query()->findOrFail((int) $this->magasinId);
        $date = Carbon::parse($this->dateMouvement);

        try {
            match ($this->formulaire) {
                'entree' => StockIntrants::entree($intrant, $magasin, (int) $this->quantite, $date, $this->moi(), $this->motif ?: null),
                'perte' => StockIntrants::corriger($intrant, $magasin, TypeMouvementIntrant::Perte, (int) $this->quantite, $date, $this->motif, $this->moi()),
                'ajustement' => StockIntrants::corriger($intrant, $magasin, TypeMouvementIntrant::Ajustement, (int) $this->quantite, $date, $this->motif, $this->moi()),
                default => abort(404),
            };
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['quantite' => $e->getMessage()]);
        }

        $this->statut = 'Mouvement enregistré.';
        $this->formulaire = null;
    }

    public function preparerContrePassation(int $id): void
    {
        $this->authorize('gerer-intrants');
        $this->resetErrorBag();
        $this->statut = '';
        $this->motifContrePassation = '';
        $this->aContrePasser = MouvementIntrant::query()->findOrFail($id)->id;
    }

    public function contrePasser(): void
    {
        $this->authorize('gerer-intrants');
        $this->resetErrorBag();

        try {
            StockIntrants::contrePasser(MouvementIntrant::query()->findOrFail((int) $this->aContrePasser), $this->motifContrePassation, $this->moi());
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motifContrePassation' => $e->getMessage()]);
        }

        $this->statut = 'Mouvement contre-passé.';
        $this->aContrePasser = null;
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $intrants = Intrant::query()->orderByDesc('actif')->orderBy('nom')->get();
        $magasins = Magasin::query()->orderBy('nom')->get();
        // Une seule requête groupée : stock[intrant][magasin] = Σ quantités.
        $stocks = MouvementIntrant::query()
            ->selectRaw('intrant_id, magasin_id, SUM(quantite) AS stock')
            ->groupBy('intrant_id', 'magasin_id')
            ->get()
            ->groupBy('intrant_id')
            ->map(fn ($lignes) => $lignes->pluck('stock', 'magasin_id')->map(fn ($s) => (int) $s));

        return view('livewire.intrants.stock-intrant', [
            'intrants' => $intrants,
            'magasins' => $magasins,
            'stocks' => $stocks,
            'mouvements' => MouvementIntrant::query()
                ->with('intrant', 'magasin', 'pret.producteur', 'auteur', 'contrePassation')
                ->orderByDesc('id')->paginate(30),
        ]);
    }
}
