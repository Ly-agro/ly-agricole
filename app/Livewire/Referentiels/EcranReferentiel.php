<?php

namespace App\Livewire\Referentiels;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Écran commun d'un référentiel : liste, création, modification. Pas de suppression :
 * on désactive (un référentiel sera cité par des producteurs, achats, lots…).
 *
 * Une sous-classe décrit ses champs, ses règles et ses colonnes ; le droit est
 * revérifié à chaque action (une action Livewire est un point d'entrée public).
 *
 * @template TModel of Model
 */
abstract class EcranReferentiel extends Component
{
    /** null = pas de formulaire ouvert ; 0 = nouvelle ligne ; sinon l'id modifié. */
    public ?int $editionId = null;

    /** @var array<string, mixed> */
    public array $donnees = [];

    /** Confirmation après enregistrement (pas `$message` : @error l'écrase). */
    public string $statut = '';

    /**
     * Les écrans de la barre d'onglets, dans l'ordre, avec le droit qui les ouvre.
     *
     * @return array<string, array{titre: string, droit: string}>
     */
    public static function onglets(): array
    {
        return [
            'referentiels.zones' => ['titre' => 'Zones', 'droit' => 'gerer-referentiels'],
            'referentiels.villages' => ['titre' => 'Villages', 'droit' => 'gerer-referentiels'],
            'referentiels.produits' => ['titre' => 'Produits', 'droit' => 'gerer-referentiels'],
            'referentiels.campagnes' => ['titre' => 'Campagnes', 'droit' => 'gerer-campagnes'],
            'referentiels.magasins' => ['titre' => 'Magasins', 'droit' => 'gerer-referentiels'],
            'referentiels.points-collecte' => ['titre' => 'Points de collecte', 'droit' => 'gerer-referentiels'],
            'referentiels.categories-depense' => ['titre' => 'Catégories de dépense', 'droit' => 'gerer-tresorerie'],
            'referentiels.parametres' => ['titre' => 'Paramètres', 'droit' => 'gerer-parametres'],
        ];
    }

    /** Premier écran de référentiel que l'utilisateur a le droit d'ouvrir, s'il y en a un. */
    public static function premierOngletAutorise(): ?string
    {
        foreach (static::onglets() as $route => $onglet) {
            if (Gate::allows($onglet['droit'])) {
                return $route;
            }
        }

        return null;
    }

    /** @return class-string<TModel> */
    abstract protected function modele(): string;

    abstract protected function titre(): string;

    /** Libellé d'une ligne dans le message de confirmation (« Zone Korhogo créée »). */
    abstract protected function nomLigne(Model $ligne): string;

    /**
     * Champs du formulaire : nom => [libelle, type (text|number|select|checkbox|date),
     * options (select), aide, live (recharger le formulaire à chaque changement, pour
     * un champ dont dépendent les options d'un autre)].
     *
     * @return array<string, array{libelle: string, type: string, options?: array<int|string, string>, aide?: string, live?: bool}>
     */
    abstract protected function champs(): array;

    /**
     * Règles de validation, par nom de champ.
     *
     * @param  TModel|null  $existant
     * @return array<string, array<int, mixed>>
     */
    abstract protected function regles(?Model $existant): array;

    /**
     * Colonnes de la liste : libellé => valeur affichée.
     *
     * @return array<string, callable(TModel): string>
     */
    abstract protected function colonnes(): array;

    protected function droit(): string
    {
        return 'gerer-referentiels';
    }

    /** Les onglets des référentiels n'ont pas de sens hors de la rubrique Référentiels. */
    protected function afficherOnglets(): bool
    {
        return true;
    }

    /** @return Builder<TModel> */
    protected function requete(): Builder
    {
        return ($this->modele())::query()->orderBy('nom');
    }

    /**
     * Valeurs par défaut d'une nouvelle ligne.
     *
     * @return array<string, mixed>
     */
    protected function valeursInitiales(): array
    {
        return array_key_exists('actif', $this->champs()) ? ['actif' => true] : [];
    }

    /**
     * Formulaire → colonnes du modèle (ex. kg saisis → grammes stockés).
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    protected function versModele(array $donnees): array
    {
        return $donnees;
    }

    /**
     * Modèle → formulaire.
     *
     * @param  TModel  $ligne
     * @return array<string, mixed>
     */
    protected function depuisModele(Model $ligne): array
    {
        $valeurs = [];
        foreach (array_keys($this->champs()) as $champ) {
            $valeur = $ligne->getAttribute($champ);
            $valeurs[$champ] = $valeur instanceof \BackedEnum ? $valeur->value : $valeur;
        }

        return $valeurs;
    }

    /**
     * Contrôles métier après la validation des champs (lever une ValidationException).
     *
     * @param  array<string, mixed>  $attributs
     * @param  TModel|null  $existant
     */
    protected function verifier(array $attributs, ?Model $existant): void {}

    /** @param  TModel  $ligne */
    protected function peutModifier(Model $ligne): bool
    {
        return true;
    }

    /**
     * Boutons supplémentaires d'une ligne : méthode Livewire => libellé.
     *
     * @param  TModel  $ligne
     * @return array<string, string>
     */
    protected function actionsLigne(Model $ligne): array
    {
        return [];
    }

    /** Route de la page, notée au chargement : pendant une action Livewire, la route est `livewire.update`. */
    #[Locked]
    public string $routePage = '';

    public function mount(): void
    {
        $this->authorize($this->droit());
        $this->routePage = (string) request()->route()?->getName();
    }

    public function nouveau(): void
    {
        $this->authorize($this->droit());

        $this->resetErrorBag();
        $this->statut = '';
        $this->donnees = array_merge(array_fill_keys(array_keys($this->champs()), ''), $this->valeursInitiales());
        $this->editionId = 0;
    }

    public function modifier(int $id): void
    {
        $this->authorize($this->droit());

        $ligne = ($this->modele())::findOrFail($id);
        abort_unless($this->peutModifier($ligne), 403);

        $this->resetErrorBag();
        $this->statut = '';
        $this->donnees = $this->depuisModele($ligne);
        $this->editionId = $ligne->getKey();
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->editionId = null;
    }

    public function enregistrer(): void
    {
        $this->authorize($this->droit());

        $existant = $this->editionId ? ($this->modele())::findOrFail($this->editionId) : null;
        if ($existant) {
            abort_unless($this->peutModifier($existant), 403);
        }

        $regles = [];
        $noms = [];
        foreach ($this->regles($existant) as $champ => $regle) {
            $regles["donnees.$champ"] = $regle;
            $noms["donnees.$champ"] = mb_strtolower($this->champs()[$champ]['libelle'] ?? $champ);
        }

        // Un champ laissé vide arrive en '' : c'est « pas de valeur », pas une valeur.
        $this->donnees = array_map(fn ($v) => $v === '' ? null : $v, $this->donnees);

        $valide = $this->validate($regles, attributes: $noms)['donnees'];
        $attributs = $this->versModele($valide);
        $this->verifier($attributs, $existant);

        if ($existant) {
            $existant->update($attributs);
            $this->statut = $this->nomLigne($existant).' : modifications enregistrées.';
        } else {
            $cree = ($this->modele())::create($attributs);
            $this->statut = $this->nomLigne($cree).' : créé(e).';
        }

        $this->editionId = null;
    }

    public function render(): View
    {
        $lignes = $this->requete()->get();

        return view('livewire.referentiels.ecran', [
            'titrePage' => $this->titre(),
            'afficherOnglets' => $this->afficherOnglets(),
            'lignes' => $lignes,
            'modifiables' => $lignes->filter(fn (Model $l) => $this->peutModifier($l))->modelKeys(),
            'actionsParLigne' => $lignes->mapWithKeys(fn (Model $l) => [$l->getKey() => $this->actionsLigne($l)])->all(),
            'champsFormulaire' => $this->champs(),
            'colonnesListe' => $this->colonnes(),
        ])->title($this->titre());
    }
}
