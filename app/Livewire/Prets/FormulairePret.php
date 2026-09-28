<?php

namespace App\Livewire\Prets;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\Parametre;
use App\Models\Parcelle;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Prets;
use App\Support\Format;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Demande de prêt (après la visite de la parcelle). Les règles — plafonds, nombre de
 * validations, art. 17.3 — sont dans App\Services\Prets.
 */
#[Title('Nouvelle demande de prêt')]
class FormulairePret extends Component
{
    use WithFileUploads;

    #[Url(as: 'producteur', except: '')]
    public string $producteurId = '';

    public string $campagneId = '';

    public string $montant = '';

    public string $forme = '';

    public string $prixReference = '';

    public string $echeance = '';

    /**
     * Cases cochées. Après un décochage, Livewire peut renvoyer des clés non
     * consécutives : d'où le array_values() à l'enregistrement.
     *
     * @var array<int|string, string>
     */
    public array $parcelleIds = [];

    public bool $partieLiee = false;

    /** @var TemporaryUploadedFile|null */
    public $accordEcrit = null;

    public function mount(): void
    {
        $this->authorize('saisir-prets');
        $this->campagneId = (string) (Campagne::query()->where('statut', StatutCampagne::Ouverte)->value('id') ?? '');
    }

    public function updatedProducteurId(): void
    {
        $this->parcelleIds = [];
    }

    public function enregistrer(): void
    {
        $this->authorize('saisir-prets');
        $this->resetErrorBag();

        $this->validate([
            'producteurId' => ['required', 'uuid', Rule::exists('producteurs', 'id')],
            'campagneId' => ['required', 'integer', Rule::exists('campagnes', 'id')],
            'montant' => ['required', Montant::regle()],
            'forme' => ['required', Rule::enum(FormePret::class)],
            'prixReference' => ['nullable', Montant::regle()],
            'echeance' => ['required', 'date', 'after_or_equal:today'],
            'parcelleIds' => ['array'],
            'parcelleIds.*' => ['uuid'],
            'accordEcrit' => [$this->partieLiee ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ], [
            'accordEcrit.required' => 'Producteur lié à la direction : joindre l\'accord écrit (contrat, art. 17.3).',
            'echeance.after_or_equal' => 'L\'échéance ne peut pas être passée.',
        ], [
            'producteurId' => 'producteur',
            'campagneId' => 'campagne',
            'prixReference' => 'prix de référence',
            'echeance' => 'échéance',
            'accordEcrit' => 'accord écrit',
        ]);

        /** @var User $auteur */
        $auteur = auth()->user();
        $chemin = $this->partieLiee ? $this->accordEcrit?->store('prets/accords', 'local') : null;

        try {
            $pret = Prets::demander([
                'producteur_id' => $this->producteurId,
                'campagne_id' => (int) $this->campagneId,
                'montant_fcfa' => (int) Montant::depuisSaisie($this->montant),
                'forme' => FormePret::from($this->forme),
                'echeance' => Carbon::parse($this->echeance),
                'prix_reference_kg_fcfa' => Montant::depuisSaisie($this->prixReference),
                'parcelle_ids' => array_values($this->parcelleIds),
                'partie_liee' => $this->partieLiee,
            ], $auteur, $chemin ?: null);
        } catch (OperationRefusee $e) {
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        session()->flash('statut', "Demande {$pret->reference} enregistrée : {$pret->validations_requises} validation(s) de la direction requise(s).");
        $this->redirectRoute('prets.fiche', $pret);
    }

    public function render(): View
    {
        $seuil = Parametre::entier(CleParametre::SeuilValidationPret);

        return view('livewire.prets.formulaire-pret', [
            'producteurs' => Producteur::query()->with('village')->where('actif', true)->orderBy('nom')->orderBy('prenoms')->get(),
            'parcelles' => $this->producteurId === ''
                ? collect()
                : Parcelle::query()->where('producteur_id', $this->producteurId)->where('actif', true)->orderBy('nom')->get(),
            'campagnes' => Campagne::query()->with('produit')->where('statut', '!=', StatutCampagne::Cloturee)->orderByDesc('debut')->get(),
            'formes' => FormePret::cases(),
            'regleValidation' => $seuil === null
                ? 'Seuil non défini : toute demande sera validée par deux personnes de la direction.'
                : 'Au-dessus de '.Format::fcfa($seuil).', deux validations de la direction ; en dessous, une.',
        ]);
    }
}
