<?php

namespace App\Livewire\Investisseurs;

use App\Enums\Role;
use App\Exceptions\OperationRefusee;
use App\Models\Apport;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\User;
use App\Services\Apports;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Apports de campagne : direction et comptabilité enregistrent qui a apporté combien
 * (contrat art. 5, 9). App\Services\Apports impose le compte dédié de la campagne.
 */
#[Title('Apports')]
class GestionApports extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $campagneId = '';

    public bool $formulaire = false;

    public string $investisseurId = '';

    public string $compteId = '';

    public string $montant = '';

    public string $dateApport = '';

    public string $motif = '';

    public ?int $aContrePasser = null;

    public string $motifContrePassation = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('gerer-apports');
        $this->campagneId = (string) (Campagne::query()->orderByDesc('debut')->value('id') ?? '');
    }

    public function ouvrir(): void
    {
        $this->authorize('gerer-apports');
        $this->resetErrorBag();
        $this->reset('investisseurId', 'compteId', 'montant', 'motif');
        $this->dateApport = Carbon::today()->toDateString();
        $this->formulaire = true;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-apports');
        $this->resetErrorBag();

        $this->validate([
            'campagneId' => ['required', 'integer', Rule::exists('campagnes', 'id')],
            'investisseurId' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'compteId' => ['required', 'integer', Rule::exists('comptes_tresorerie', 'id')],
            'montant' => ['required', Montant::regle()],
            'dateApport' => ['required', 'date', 'before_or_equal:today'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], attributes: ['campagneId' => 'campagne', 'investisseurId' => 'investisseur', 'compteId' => 'compte', 'dateApport' => 'date']);

        /** @var User $auteur */
        $auteur = auth()->user();

        try {
            Apports::enregistrer(
                $this->investisseurId === '' ? null : (int) $this->investisseurId,
                (int) $this->campagneId, (int) $this->compteId, (int) Montant::depuisSaisie($this->montant),
                Carbon::parse($this->dateApport), $auteur, $this->motif ?: null,
            );
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['montant' => $e->getMessage()]);
        }

        $this->formulaire = false;
        $this->statut = 'Apport enregistré.';
    }

    public function preparerContrePassation(int $id): void
    {
        $this->authorize('gerer-apports');
        $this->resetErrorBag();
        $this->motifContrePassation = '';
        $this->aContrePasser = $id;
    }

    public function contrePasser(): void
    {
        $this->authorize('gerer-apports');
        $this->resetErrorBag();

        /** @var User $auteur */
        $auteur = auth()->user();

        try {
            Apports::contrePasser(Apport::query()->findOrFail($this->aContrePasser), $this->motifContrePassation, $auteur);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['motifContrePassation' => $e->getMessage()]);
        }

        $this->aContrePasser = null;
        $this->statut = 'Apport contre-passé.';
    }

    public function render(): View
    {
        $campagne = $this->campagneId === '' ? null : Campagne::query()->with('produit')->find((int) $this->campagneId);

        return view('livewire.investisseurs.gestion-apports', [
            'campagnes' => Campagne::query()->with('produit')->orderByDesc('debut')->get(),
            'campagne' => $campagne,
            'comptesDedies' => $campagne ? CompteTresorerie::query()->where('campagne_id', $campagne->id)->orderBy('nom')->get() : collect(),
            'investisseurs' => User::query()->where('role', Role::Investisseur->value)->where('actif', true)->orderBy('nom')->get(),
            'apports' => $campagne
                ? Apport::query()->where('campagne_id', $campagne->id)->with('investisseur', 'auteur', 'contrePassation')
                    ->orderByDesc('id')->get()
                : collect(),
            'repartition' => $campagne ? Apports::repartition($campagne) : null,
        ]);
    }
}
