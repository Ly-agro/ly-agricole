<?php

namespace App\Livewire\Depenses;

use App\Enums\StatutDepense;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\User;
use App\Services\Depenses;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Saisie d'une dépense avec son justificatif (obligatoire). Le seuil et la séparation
 * des tâches sont appliqués par App\Services\Depenses, pas par cet écran.
 */
#[Title('Nouvelle dépense')]
class FormulaireDepense extends Component
{
    use WithFileUploads;

    public const JUSTIFICATIF_MAX_KO = 8192;

    public string $categorieId = '';

    public string $compteId = '';

    public string $montant = '';

    public string $dateDepense = '';

    public string $beneficiaire = '';

    public string $description = '';

    public string $campagneId = '';

    /** @var TemporaryUploadedFile|null */
    public $justificatif = null;

    public function mount(): void
    {
        $this->authorize('saisir-depenses');
        $this->dateDepense = Carbon::today()->toDateString();
    }

    public function updatedJustificatif(): void
    {
        $this->validateOnly('justificatif', ['justificatif' => $this->regleJustificatif()], attributes: ['justificatif' => 'justificatif']);
    }

    /** @return list<string> */
    private function regleJustificatif(): array
    {
        return ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::JUSTIFICATIF_MAX_KO];
    }

    public function enregistrer(): void
    {
        $this->authorize('saisir-depenses');

        $this->validate([
            'categorieId' => ['required', 'integer', Rule::exists('categories_depense', 'id')->where('actif', true)],
            'compteId' => ['required', 'integer', Rule::exists('comptes_tresorerie', 'id')],
            'montant' => ['required', Montant::regle()],
            'dateDepense' => ['required', 'date', 'before_or_equal:today'],
            'beneficiaire' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'campagneId' => ['nullable', 'integer', Rule::exists('campagnes', 'id')],
            'justificatif' => $this->regleJustificatif(),
        ], [
            'justificatif.required' => 'Le justificatif (photo du reçu ou PDF) est obligatoire.',
            'dateDepense.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ], [
            'categorieId' => 'catégorie',
            'compteId' => 'compte',
            'dateDepense' => 'date',
            'beneficiaire' => 'bénéficiaire',
            'campagneId' => 'campagne',
            'justificatif' => 'justificatif',
        ]);

        /** @var User $auteur */
        $auteur = auth()->user();
        $chemin = $this->justificatif->store('depenses/justificatifs', 'local');

        try {
            $depense = Depenses::saisir([
                'categorie_id' => (int) $this->categorieId,
                'compte_id' => (int) $this->compteId,
                'montant_fcfa' => (int) Montant::depuisSaisie($this->montant),
                'date_depense' => Carbon::parse($this->dateDepense),
                'beneficiaire' => $this->beneficiaire,
                'description' => $this->description ?: null,
                'campagne_id' => $this->campagneId === '' ? null : (int) $this->campagneId,
            ], (string) $chemin, $auteur);
        } catch (OperationRefusee $e) {
            Storage::disk('local')->delete((string) $chemin);
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete((string) $chemin);
            throw $e;
        }

        session()->flash('statut', $depense->statut === StatutDepense::Payee
            ? 'Dépense enregistrée et payée.'
            : 'Dépense enregistrée : elle attend la validation d\'une autre personne.');
        $this->redirectRoute('depenses');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.depenses.formulaire-depense', [
            'categories' => CategorieDepense::query()->where('actif', true)->orderBy('nom')->get(),
            'comptes' => CompteTresorerie::query()->orderBy('nom')->get()
                ->filter(fn (CompteTresorerie $c) => Depenses::peutPayerDepuis($user, $c)),
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
            'regleSeuil' => Depenses::libelleSeuil(),
        ]);
    }
}
