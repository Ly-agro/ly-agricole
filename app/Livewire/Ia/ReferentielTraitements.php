<?php

namespace App\Livewire\Ia;

use App\Exceptions\OperationRefusee;
use App\Models\FicheTraitement;
use App\Models\Produit;
use App\Models\User;
use App\Services\Ia\Referentiel;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Référentiel des traitements : l'agronome le tient ; la direction le lit. Vide au départ,
 * jamais rempli de mémoire : c'est la seule source des produits et des doses.
 */
#[Title('Référentiel des traitements')]
class ReferentielTraitements extends Component
{
    #[Url(as: 'culture', except: '')]
    public string $produitId = '';

    public bool $formulaire = false;

    public ?int $ficheId = null;

    /** @var array<string, string> */
    public array $champs = [];

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('voir-ia');
    }

    public function ouvrir(?int $id = null): void
    {
        $this->authorize('gerer-referentiel-traitements');
        $this->resetErrorBag();
        $this->statut = '';
        $this->ficheId = $id;
        $fiche = $id === null ? null : FicheTraitement::query()->findOrFail($id);
        // Chaque valeur a un repli : on n'a qu'à tout mettre en texte pour le formulaire.
        $this->champs = array_map(fn (int|string $v) => (string) $v, [
            'produit_id' => $fiche->produit_id ?? ($this->produitId !== '' ? (int) $this->produitId : ''),
            'type' => $fiche->type ?? 'pratique',
            'cible' => $fiche->cible ?? '',
            'titre' => $fiche->titre ?? '',
            'description' => $fiche->description ?? '',
            'nom_commercial' => $fiche->nom_commercial ?? '',
            'matiere_active' => $fiche->matiere_active ?? '',
            'dose' => $fiche->dose ?? '',
            'passages' => $fiche->passages ?? '',
            'delai_avant_recolte_jours' => $fiche->delai_avant_recolte_jours ?? '',
            'toxicite_humaine' => $fiche->toxicite_humaine ?? '',
            'protection' => $fiche->protection ?? '',
            'effet_abeilles' => $fiche->effet_abeilles ?? '',
            'reference_homologation' => $fiche->reference_homologation ?? '',
            'statut' => $fiche->statut ?? 'autorisee',
        ]);
        $this->formulaire = true;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-referentiel-traitements');
        $this->resetErrorBag();

        $d = $this->validate([
            'champs.produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'champs.type' => ['required', Rule::in(array_keys(FicheTraitement::TYPES))],
            'champs.cible' => ['required', 'string', 'max:255'],
            'champs.titre' => ['required', 'string', 'max:255'],
            'champs.description' => ['nullable', 'string', 'max:2000'],
            'champs.nom_commercial' => ['nullable', 'string', 'max:255'],
            'champs.matiere_active' => ['nullable', 'string', 'max:255'],
            'champs.dose' => ['nullable', 'string', 'max:255'],
            'champs.passages' => ['nullable', 'integer', 'min:1', 'max:50'],
            'champs.delai_avant_recolte_jours' => ['nullable', 'integer', 'min:0', 'max:365'],
            'champs.toxicite_humaine' => ['nullable', 'string', 'max:255'],
            'champs.protection' => ['nullable', 'string', 'max:255'],
            'champs.effet_abeilles' => ['nullable', 'string', 'max:255'],
            'champs.reference_homologation' => ['nullable', 'string', 'max:255'],
            'champs.statut' => ['required', Rule::in(array_keys(FicheTraitement::STATUTS))],
        ], attributes: ['champs.titre' => 'intitulé', 'champs.cible' => 'cible', 'champs.produit_id' => 'culture'])['champs'];

        $d['produit_id'] = (int) $d['produit_id'];
        $d['passages'] = ($d['passages'] ?? '') === '' ? null : (int) $d['passages'];
        $d['delai_avant_recolte_jours'] = ($d['delai_avant_recolte_jours'] ?? '') === '' ? null : (int) $d['delai_avant_recolte_jours'];

        /** @var User $moi */
        $moi = auth()->user();
        try {
            /** @var array{produit_id: int, type: string, cible: string, titre: string} $d */
            $fiche = Referentiel::enregistrer($d, $moi, $this->ficheId === null ? null : FicheTraitement::query()->findOrFail($this->ficheId));
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['champs.titre' => $e->getMessage()]);
        }

        $this->formulaire = false;
        $this->statut = $fiche->proposable()
            ? 'Fiche enregistrée : l\'IA peut la citer.'
            : 'Fiche enregistrée, mais PAS proposable : '.($fiche->statut !== 'autorisee' ? 'elle n\'est pas autorisée.' : 'il manque '.implode(', ', $fiche->manquants()).'.');
    }

    public function render(): View
    {
        return view('livewire.ia.referentiel-traitements', [
            'produits' => Produit::query()->orderBy('nom')->get(),
            'fiches' => FicheTraitement::query()->with('produit', 'validateur')
                ->when($this->produitId !== '', fn ($q) => $q->where('produit_id', (int) $this->produitId))
                ->orderBy('produit_id')->orderByRaw("CASE type WHEN 'pratique' THEN 0 WHEN 'biologique' THEN 1 ELSE 2 END")->orderBy('cible')->get(),
            'peutModifier' => auth()->user()?->can('gerer-referentiel-traitements') ?? false,
        ]);
    }
}
