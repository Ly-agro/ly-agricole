<?php

namespace App\Livewire\Budget;

use App\Enums\PosteBudget;
use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\LigneBudget;
use App\Models\User;
use App\Services\Budgets;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Budget de campagne, prévu contre réel : la direction fixe le prévu par poste, la
 * comptabilité le suit. Le réel vient de App\Services\Budgets::suivi().
 */
#[Title('Budget')]
class SuiviBudget extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $campagneId = '';

    public bool $formulaire = false;

    /** `achats`, `prets` ou `categorie-<id>`. */
    public string $poste = '';

    public string $montant = '';

    public string $note = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('voir-budget');
        if ($this->campagneId === '') {
            $this->campagneId = (string) (Campagne::query()->orderByDesc('debut')->value('id') ?? '');
        }
    }

    public function updatedCampagneId(): void
    {
        $this->formulaire = false;
        $this->statut = '';
    }

    public function ouvrir(string $poste = ''): void
    {
        $this->authorize('gerer-budget');
        $this->resetErrorBag();
        $this->statut = '';
        $this->poste = $poste;
        $this->montant = '';
        $this->note = '';

        $ligne = $this->ligneExistante($poste);
        if ($ligne !== null) {
            $this->montant = number_format($ligne->montant_fcfa, 0, '', ' ');
            $this->note = (string) $ligne->note;
        }
        $this->formulaire = true;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-budget');
        $this->resetErrorBag();

        $this->validate([
            'poste' => ['required', 'string', 'regex:/^(achats|prets|categorie-\d+)$/'],
            'montant' => ['required', function (string $attribut, mixed $valeur, \Closure $echec) {
                // Zéro est permis : « rien de prévu sur ce poste » est une décision.
                if (Montant::depuisSaisie(is_scalar($valeur) ? (string) $valeur : null) === null) {
                    $echec('Le montant doit être un nombre entier de FCFA, sans virgule ni point (ex. 1 500 000).');
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['poste' => 'poste']);

        $campagne = Campagne::query()->findOrFail((int) $this->campagneId);
        [$poste, $categorieId] = $this->decoderPoste($this->poste);

        /** @var User $auteur */
        $auteur = auth()->user();

        try {
            Budgets::definir($campagne, $poste, $categorieId, (int) Montant::depuisSaisie($this->montant), $auteur, $this->note ?: null);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        $this->formulaire = false;
        $this->statut = 'Budget enregistré.';
    }

    /** @return array{PosteBudget, int|null} */
    private function decoderPoste(string $poste): array
    {
        return str_starts_with($poste, 'categorie-')
            ? [PosteBudget::Categorie, (int) substr($poste, strlen('categorie-'))]
            : [PosteBudget::from($poste), null];
    }

    private function ligneExistante(string $poste): ?LigneBudget
    {
        if ($this->campagneId === '' || preg_match('/^(achats|prets|categorie-\d+)$/', $poste) !== 1) {
            return null;
        }
        [$type, $categorieId] = $this->decoderPoste($poste);

        return LigneBudget::query()->where('campagne_id', (int) $this->campagneId)
            ->where('poste', $type->value)->where('categorie_id', $categorieId)->first();
    }

    public function render(): View
    {
        $campagne = $this->campagneId === '' ? null : Campagne::query()->with('produit')->find((int) $this->campagneId);

        return view('livewire.budget.suivi-budget', [
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
            'campagne' => $campagne,
            'suivi' => $campagne ? Budgets::suivi($campagne) : null,
            'modifiable' => $campagne !== null && $campagne->statut !== StatutCampagne::Cloturee && auth()->user()?->can('gerer-budget'),
            'categories' => CategorieDepense::query()->where('actif', true)->where('exclue_fonds_campagne', false)->orderBy('nom')->get(),
        ]);
    }
}
