<?php

namespace App\Livewire\Prets;

use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\StatutPret;
use App\Exceptions\OperationRefusee;
use App\Models\CompteTresorerie;
use App\Models\Pret;
use App\Models\User;
use App\Services\Prets;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Fiche d'un prêt : validations (par d'autres que l'auteur), refus, décaissements par
 * tranches. Chaque action passe par App\Services\Prets, qui revérifie tout.
 */
class FichePret extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $pretId = '';

    public string $statut = '';

    public bool $refusOuvert = false;

    public string $motifRefus = '';

    public bool $decaissementOuvert = false;

    public string $compteId = '';

    public string $montant = '';

    public string $dateDecaissement = '';

    public string $reference = '';

    /** @var TemporaryUploadedFile|null */
    public $recu = null;

    public function mount(Pret $pret): void
    {
        $this->authorize('voir-prets');
        $this->pretId = $pret->id;
        $this->statut = (string) session('statut', '');
    }

    private function pret(): Pret
    {
        return Pret::query()->findOrFail($this->pretId);
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function valider(): void
    {
        $this->authorize('valider-prets');
        $this->resetErrorBag();

        try {
            $pret = Prets::valider($this->pret(), $this->moi());
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }

        $this->statut = $pret->statut === StatutPret::Valide
            ? 'Prêt validé : il peut être décaissé.'
            : 'Validation enregistrée : une autre personne de la direction doit encore valider.';
    }

    public function ouvrirRefus(): void
    {
        $this->authorize('valider-prets');
        $this->refusOuvert = true;
        $this->motifRefus = '';
    }

    public function refuser(): void
    {
        $this->authorize('valider-prets');
        $this->resetErrorBag();

        try {
            Prets::refuser($this->pret(), $this->moi(), $this->motifRefus);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motifRefus' => $e->getMessage()]);
        }

        $this->refusOuvert = false;
        $this->statut = 'Demande refusée.';
    }

    public function ouvrirDecaissement(): void
    {
        $this->authorize('decaisser-prets');
        $this->resetErrorBag();
        $this->reset('compteId', 'reference', 'recu');
        $this->montant = (string) $this->pret()->resteADecaisser();
        $this->dateDecaissement = Carbon::today()->toDateString();
        $this->decaissementOuvert = true;
    }

    public function decaisser(): void
    {
        $this->authorize('decaisser-prets');
        $this->resetErrorBag();
        $pret = $this->pret();
        $mode = $pret->forme === FormePret::MobileMoney ? ModeDecaissement::MobileMoney : ModeDecaissement::Especes;

        $this->validate([
            'compteId' => ['required', 'integer', Rule::exists('comptes_tresorerie', 'id')],
            'montant' => ['required', Montant::regle()],
            'dateDecaissement' => ['required', 'date', 'before_or_equal:today'],
            'reference' => [$mode === ModeDecaissement::MobileMoney ? 'required' : 'nullable', 'string', 'max:100'],
            'recu' => [$mode === ModeDecaissement::Especes ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [
            'recu.required' => 'Versement en espèces : joindre le reçu signé par le producteur.',
            'reference.required' => 'Versement Mobile Money : la référence de la transaction est obligatoire.',
            'dateDecaissement.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ], ['compteId' => 'compte', 'dateDecaissement' => 'date', 'reference' => 'référence', 'recu' => 'reçu']);

        $chemin = $this->recu?->store('prets/recus', 'local');

        try {
            Prets::decaisser($pret, [
                'compte_id' => (int) $this->compteId,
                'mode' => $mode,
                'montant_fcfa' => (int) Montant::depuisSaisie($this->montant),
                'date' => Carbon::parse($this->dateDecaissement),
                'reference' => $this->reference ?: null,
            ], $this->moi(), $chemin ?: null);
        } catch (OperationRefusee $e) {
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        $this->decaissementOuvert = false;
        $this->statut = 'Versement enregistré ; la trésorerie est à jour.';
    }

    public function render(): View
    {
        $pret = Pret::query()
            ->with(['producteur.village.zone', 'campagne.produit', 'auteur', 'validations.user', 'parcelles',
                'decaissements' => fn ($q) => $q->with('compte', 'auteur', 'mouvement.contrePassation')->orderBy('id')])
            ->findOrFail($this->pretId);
        $mode = $pret->forme === FormePret::MobileMoney ? ModeDecaissement::MobileMoney : ModeDecaissement::Especes;
        $moi = $this->moi();

        return view('livewire.prets.fiche-pret', [
            'pret' => $pret,
            'decaisse' => $pret->montantDecaisse(),
            'surface' => $pret->surfaceFinanceeM2(),
            'mode' => $mode,
            'comptes' => CompteTresorerie::query()->where('actif', true)->whereIn('type', $mode->typesDeCompte())->orderBy('nom')->get(),
            'peutValider' => $pret->statut === StatutPret::Demande && $moi->can('valider-prets')
                && $pret->cree_par !== $moi->id && ! $pret->validations->contains('user_id', $moi->id),
            'peutDecaisser' => $pret->statut === StatutPret::Valide && $moi->can('decaisser-prets'),
        ])->title('Prêt '.$pret->reference);
    }
}
