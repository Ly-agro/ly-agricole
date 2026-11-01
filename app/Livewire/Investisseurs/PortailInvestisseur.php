<?php

namespace App\Livewire\Investisseurs;

use App\Enums\StatutCampagne;
use App\Enums\StatutDeclarationApport;
use App\Exceptions\OperationRefusee;
use App\Models\Apport;
use App\Models\Campagne;
use App\Models\DeclarationApport;
use App\Models\User;
use App\Services\Apports;
use App\Services\DeclarationsApport;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ce qu'un investisseur voit de son compte : ses apports, campagne par campagne, et sa
 * part de l'ensemble des apports d'investisseurs sur chacune. PAS un calcul de
 * résultat ni de quote-part (contrat art. 10 à 14, texte non disponible — voir
 * App\Services\Apports) : l'écran le dit explicitement, pour ne rien laisser croire de
 * plus que ce qui est garanti.
 */
#[Title('Mon investissement')]
class PortailInvestisseur extends Component
{
    public bool $formulaireDeclaration = false;

    public string $campagneDeclarationId = '';

    public string $montantDeclaration = '';

    public string $dateVirement = '';

    public string $referenceVirement = '';

    public string $statutDeclaration = '';

    public function mount(): void
    {
        $this->authorize('voir-portail-investisseur');
        $this->campagneDeclarationId = (string) (Campagne::query()->where('statut', StatutCampagne::Ouverte)->orderByDesc('debut')->value('id') ?? '');
        $this->dateVirement = Carbon::today()->toDateString();
    }

    public function ouvrirDeclaration(): void
    {
        $this->authorize('voir-portail-investisseur');
        $this->resetErrorBag();
        $this->reset('montantDeclaration', 'referenceVirement', 'statutDeclaration');
        $this->dateVirement = Carbon::today()->toDateString();
        $this->formulaireDeclaration = true;
    }

    public function declarerApport(): void
    {
        $this->authorize('voir-portail-investisseur');
        $this->resetErrorBag();

        $this->validate([
            'campagneDeclarationId' => ['required', 'integer', Rule::exists('campagnes', 'id')->where('statut', StatutCampagne::Ouverte->value)],
            'montantDeclaration' => ['required', Montant::regle()],
            'dateVirement' => ['required', 'date', 'before_or_equal:today'],
            'referenceVirement' => ['nullable', 'string', 'max:150'],
        ], attributes: [
            'campagneDeclarationId' => 'campagne',
            'montantDeclaration' => 'montant',
            'dateVirement' => 'date du virement',
            'referenceVirement' => 'référence du virement',
        ]);

        try {
            DeclarationsApport::declarer(
                $this->moi(),
                (int) $this->campagneDeclarationId,
                (int) Montant::depuisSaisie($this->montantDeclaration),
                Carbon::parse($this->dateVirement),
                $this->referenceVirement,
            );
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['montantDeclaration' => $e->getMessage()]);
        }

        $this->formulaireDeclaration = false;
        $this->statutDeclaration = 'Déclaration enregistrée, en attente de confirmation du bureau.';
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $moi = $this->moi();

        $campagnes = Apport::query()->where('investisseur_id', $moi->id)
            ->with('campagne.produit')->get()->pluck('campagne')->unique('id')->sortByDesc('debut');

        $lignes = $campagnes->map(function ($campagne) use ($moi) {
            $repartition = Apports::repartition($campagne);
            $mesApports = Apport::query()->where('investisseur_id', $moi->id)->where('campagne_id', $campagne->id)
                ->orderByDesc('date_apport')->get();
            $maLigne = $repartition['lignes']->firstWhere(fn ($l) => $l['investisseur']?->id === $moi->id);

            return [
                'campagne' => $campagne,
                'monApport' => $maLigne['montant'] ?? 0,
                'partPourMille' => $maLigne['part_pour_mille'] ?? 0,
                'totalInvestisseurs' => $repartition['parInvestisseurs'],
                'mesApports' => $mesApports,
            ];
        });

        return view('livewire.investisseurs.portail-investisseur', [
            'lignes' => $lignes,
            'campagnesOuvertes' => Campagne::query()->with('produit')->where('statut', StatutCampagne::Ouverte)->orderByDesc('debut')->get(),
            'declarations' => DeclarationApport::query()->where('investisseur_id', $moi->id)
                ->with('campagne.produit')->orderByDesc('id')->get(),
            'declarationsEnAttente' => DeclarationApport::query()->where('investisseur_id', $moi->id)
                ->where('statut', StatutDeclarationApport::EnAttente)->exists(),
        ]);
    }
}
