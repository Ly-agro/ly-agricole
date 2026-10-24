<?php

namespace App\Livewire\Fiabilite;

use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\Producteur;
use App\Models\User;
use App\Services\DecisionsPlafond;
use App\Services\FiabiliteProducteur;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Historique de remboursement d'un producteur, plafond proposé par le logiciel (règle
 * provisoire, question 35) et DÉCISION de la direction, avec son historique.
 */
#[Title('Fiabilité du producteur')]
class FicheFiabilite extends Component
{
    public Producteur $producteur;

    public bool $formulaire = false;

    public string $plafondRetenu = '';

    public string $motifDecision = '';

    public string $campagneDecision = '';

    public string $statut = '';

    public function mount(Producteur $producteur): void
    {
        $this->authorize('voir-fiabilite');
        $this->producteur = $producteur;
    }

    public function ouvrirDecision(): void
    {
        $this->authorize('decider-plafond');
        $this->resetErrorBag();
        $propose = FiabiliteProducteur::fiche($this->producteur)['plafond_propose'];
        $this->plafondRetenu = $propose === null ? '' : (string) $propose;
        $this->formulaire = true;
    }

    public function fermerDecision(): void
    {
        $this->formulaire = false;
        $this->resetErrorBag();
    }

    public function decider(): void
    {
        $this->authorize('decider-plafond');
        $this->resetErrorBag();

        $this->validate([
            // 0 est un plafond valide (« pas de nouveau prêt ») : la règle Montant::regle() le refuserait.
            'plafondRetenu' => ['required', 'regex:/^\s*[0-9][0-9\s\x{00A0}\x{202F}]*$/u'],
            'motifDecision' => ['nullable', 'string', 'max:1000'],
            'campagneDecision' => ['nullable', 'integer', Rule::exists('campagnes', 'id')],
        ], [], ['plafondRetenu' => 'plafond retenu', 'motifDecision' => 'motif', 'campagneDecision' => 'campagne']);

        try {
            DecisionsPlafond::enregistrer(
                $this->producteur,
                (int) Montant::depuisSaisie($this->plafondRetenu),
                $this->motifDecision,
                $this->campagneDecision === '' ? null : Campagne::query()->findOrFail((int) $this->campagneDecision),
                $this->moi(),
            );
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['plafondRetenu' => $e->getMessage()]);
        }

        $this->reset('plafondRetenu', 'motifDecision', 'campagneDecision');
        $this->formulaire = false;
        $this->statut = 'Décision enregistrée : elle remplace la précédente, l\'historique est conservé.';
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.fiabilite.fiche-fiabilite', [
            'fiche' => FiabiliteProducteur::fiche($this->producteur),
            'decisions' => DecisionsPlafond::historique($this->producteur),
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
        ]);
    }
}
