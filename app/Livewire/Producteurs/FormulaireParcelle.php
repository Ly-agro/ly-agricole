<?php

namespace App\Livewire\Producteurs;

use App\Models\Parcelle;
use App\Models\Producteur;
use App\Models\Produit;
use App\Services\Geo\Contour;
use App\Support\Format;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Parcelle d'un producteur. Le contour s'importe (fichier GeoJSON ou texte collé) ;
 * la surface en découle. Relevé GPS en marchant : appli terrain, semaine 9.
 */
class FormulaireParcelle extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $producteurId = '';

    #[Locked]
    public ?string $parcelleId = null;

    public string $nom = '';

    public string $produitId = '';

    public string $anneePlantation = '';

    public string $nbArbres = '';

    public string $sol = '';

    /** '' = inconnu, '1' = oui, '0' = non. */
    public string $accesEau = '';

    /** @var TemporaryUploadedFile|null */
    public $fichierContour = null;

    public string $texteContour = '';

    /**
     * Géométrie lue et vérifiée côté serveur ; verrouillée : le navigateur ne peut pas
     * la remplacer.
     *
     * @var array{type: string, coordinates: array<mixed>}|null
     */
    #[Locked]
    public ?array $contour = null;

    public function mount(Producteur $producteur, ?Parcelle $parcelle = null): void
    {
        $this->authorize('gerer-producteurs');
        $this->producteurId = $producteur->id;

        if ($parcelle?->exists) {
            abort_unless($parcelle->producteur_id === $producteur->id, 404);
            $this->parcelleId = $parcelle->id;
            $this->nom = $parcelle->nom;
            $this->produitId = (string) ($parcelle->produit_id ?? '');
            $this->anneePlantation = (string) ($parcelle->annee_plantation ?? '');
            $this->nbArbres = (string) ($parcelle->nb_arbres ?? '');
            $this->sol = $parcelle->sol ?? '';
            $this->accesEau = $parcelle->acces_eau === null ? '' : ($parcelle->acces_eau ? '1' : '0');
            $this->contour = $parcelle->contour;
        }
    }

    public function updatedFichierContour(): void
    {
        $this->validateOnly('fichierContour', ['fichierContour' => ['file', 'max:2048']]);

        $extension = strtolower($this->fichierContour?->getClientOriginalExtension() ?? '');
        if (! in_array($extension, ['geojson', 'json'], true)) {
            $this->fichierContour = null;
            throw ValidationException::withMessages(['fichierContour' => 'Choisir un fichier .geojson ou .json.']);
        }

        $this->lireContour((string) $this->fichierContour?->get(), 'fichierContour');
        $this->fichierContour = null;
    }

    public function lireTexte(): void
    {
        $this->lireContour($this->texteContour, 'texteContour');
    }

    public function retirerContour(): void
    {
        $this->contour = null;
    }

    private function lireContour(string $texte, string $champ): void
    {
        $this->resetErrorBag(['fichierContour', 'texteContour']);

        try {
            $this->contour = Contour::depuisGeojson($texte)->geometrie;
            $this->texteContour = '';
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$champ => $e->getMessage()]);
        }
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-producteurs');

        $producteur = Producteur::findOrFail($this->producteurId);
        $existante = $this->parcelleId ? Parcelle::query()->whereBelongsTo($producteur)->findOrFail($this->parcelleId) : null;

        $this->validate([
            'nom' => ['required', 'string', 'max:255',
                Rule::unique('parcelles', 'nom')->where('producteur_id', $producteur->id)->ignore($existante)],
            'produitId' => ['nullable', 'integer', Rule::exists('produits', 'id')],
            'anneePlantation' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'nbArbres' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sol' => ['nullable', 'string', 'max:255'],
            'accesEau' => ['nullable', Rule::in(['0', '1'])],
        ], attributes: [
            'nom' => 'nom',
            'produitId' => 'culture',
            'anneePlantation' => 'année de plantation',
            'nbArbres' => 'nombre d\'arbres',
            'sol' => 'type de sol',
        ]);

        $attributs = [
            'nom' => trim($this->nom),
            'produit_id' => $this->produitId === '' ? null : (int) $this->produitId,
            'annee_plantation' => $this->anneePlantation === '' ? null : (int) $this->anneePlantation,
            'nb_arbres' => $this->nbArbres === '' ? null : (int) $this->nbArbres,
            'sol' => trim($this->sol) ?: null,
            'acces_eau' => $this->accesEau === '' ? null : $this->accesEau === '1',
            'contour' => $this->contour,
        ];

        if ($existante) {
            $existante->update($attributs);
            session()->flash('statut', "Parcelle « {$existante->nom} » modifiée.");
        } else {
            $parcelle = Parcelle::create($attributs + ['producteur_id' => $producteur->id, 'cree_par' => auth()->id()]);
            session()->flash('statut', "Parcelle « {$parcelle->nom} » ajoutée.");
        }

        $this->redirectRoute('producteurs.fiche', $producteur);
    }

    public function render(): View
    {
        $forme = $this->contour === null ? null : Contour::depuisGeometrie($this->contour);

        return view('livewire.producteurs.formulaire-parcelle', [
            'producteur' => Producteur::findOrFail($this->producteurId),
            'produits' => Produit::query()->where('actif', true)->orderBy('nom')->get(),
            'surface' => $forme === null ? null : Format::hectares($forme->surfaceM2()),
            'pointsSvg' => $forme?->pointsSvg() ?? [],
        ])->title($this->parcelleId ? 'Modifier une parcelle' : 'Nouvelle parcelle');
    }
}
