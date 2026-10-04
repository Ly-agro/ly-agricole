<?php

namespace App\Livewire\Publications;

use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Models\Actualite;
use App\Models\Campagne;
use App\Models\Produit;
use App\Models\SourceActualites;
use App\Models\User;
use App\Services\Publications;
use App\Services\RecuperationActualites;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ce que la vitrine publique affiche, saisi par la direction : prix bord-champ (café, cacao,
 * anacarde, autres produits) avec leur source et leur date, et actualités. Un prix affiché est une
 * information : ce n'est pas le prix officiel d'une campagne.
 */
#[Title('Vitrine : prix et actualités')]
class GestionPublications extends Component
{
    #[Url(as: 'onglet', except: 'prix')]
    public string $onglet = 'prix';

    // --- prix ---
    public string $produitId = '';

    public string $prixKg = '';

    public string $dateEffet = '';

    public string $source = '';

    public string $sourceUrl = '';

    public string $note = '';

    // --- actualités ---
    public ?int $actualiteId = null;

    public bool $formulaireActualite = false;

    public string $titre = '';

    public string $contenu = '';

    public bool $publie = false;

    public string $fluxNom = '';

    public string $fluxUrl = '';

    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('gerer-publications');
        $this->dateEffet = Carbon::today()->toDateString();
    }

    public function updatedOnglet(): void
    {
        if (! in_array($this->onglet, ['prix', 'actualites', 'sources'], true)) {
            $this->onglet = 'prix';
        }
        $this->statut = '';
        $this->resetErrorBag();
    }

    /** Pré-remplit avec le prix officiel de la campagne ouverte du produit, s'il y en a un. */
    public function reprendrePrixCampagne(): void
    {
        $this->authorize('gerer-publications');
        $campagne = Campagne::query()->where('produit_id', (int) $this->produitId)->where('statut', StatutCampagne::Ouverte)->first();

        if ($campagne === null || $campagne->prix_officiel_kg_fcfa === null) {
            $this->statut = 'Aucun prix officiel n\'est fixé pour la campagne ouverte de ce produit.';

            return;
        }
        $this->prixKg = (string) $campagne->prix_officiel_kg_fcfa;
        $this->source = 'Prix officiel de la campagne '.$campagne->code;
        $this->statut = '';
    }

    public function publierPrix(): void
    {
        $this->authorize('gerer-publications');
        $this->resetErrorBag();
        $this->statut = '';

        $this->validate([
            'produitId' => ['required', 'integer', Rule::exists('produits', 'id')],
            'prixKg' => ['required', Montant::regle()],
            'dateEffet' => ['required', 'date', 'before_or_equal:today'],
            'source' => ['required', 'string', 'min:3', 'max:255'],
            'sourceUrl' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['produitId' => 'produit', 'prixKg' => 'prix', 'dateEffet' => 'date d\'effet', 'sourceUrl' => 'lien de la source']);

        try {
            Publications::publierPrix(
                Produit::query()->findOrFail((int) $this->produitId),
                (int) Montant::depuisSaisie($this->prixKg),
                Carbon::parse($this->dateEffet),
                $this->source,
                $this->sourceUrl,
                $this->note,
                $this->moi(),
            );
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['prixKg' => $e->getMessage()]);
        }

        $this->reset('prixKg', 'source', 'sourceUrl', 'note');
        $this->statut = 'Prix publié : il s\'affiche sur la vitrine avec sa source et sa date. L\'ancien reste dans l\'historique.';
    }

    public function nouvelleActualite(): void
    {
        $this->authorize('gerer-publications');
        $this->resetErrorBag();
        $this->reset('actualiteId', 'titre', 'contenu', 'publie');
        $this->formulaireActualite = true;
    }

    public function modifierActualite(int $id): void
    {
        $this->authorize('gerer-publications');
        $this->resetErrorBag();
        $actualite = Actualite::query()->findOrFail($id);
        $this->actualiteId = $actualite->id;
        $this->titre = $actualite->titre;
        $this->contenu = $actualite->contenu;
        $this->publie = $actualite->publie;
        $this->formulaireActualite = true;
    }

    public function annulerActualite(): void
    {
        $this->reset('actualiteId', 'titre', 'contenu', 'publie', 'formulaireActualite');
        $this->resetErrorBag();
    }

    public function enregistrerActualite(): void
    {
        $this->authorize('gerer-publications');
        $this->resetErrorBag();

        $this->validate([
            'titre' => ['required', 'string', 'max:200'],
            'contenu' => ['required', 'string', 'min:10', 'max:10000'],
            'publie' => ['boolean'],
        ], [], ['titre' => 'titre', 'contenu' => 'texte']);

        $existante = $this->actualiteId === null ? null : Actualite::query()->findOrFail($this->actualiteId);
        try {
            Publications::enregistrerActualite(['titre' => $this->titre, 'contenu' => $this->contenu, 'publie' => $this->publie], $this->moi(), $existante);
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['titre' => $e->getMessage()]);
        }

        $this->statut = $this->publie ? 'Actualité publiée sur la vitrine.' : 'Brouillon enregistré : il ne s\'affiche pas sur la vitrine.';
        $this->annulerActualite();
    }

    public function basculerPublication(int $id): void
    {
        $this->authorize('gerer-publications');
        $actualite = Actualite::query()->findOrFail($id);
        // L'état AVANT : le modèle est déjà mis à jour quand le service rend la main.
        $etaitPubliee = $actualite->publie;

        Publications::enregistrerActualite([
            'titre' => $actualite->titre, 'contenu' => $actualite->contenu, 'publie' => ! $etaitPubliee,
        ], $this->moi(), $actualite);

        $this->statut = $etaitPubliee ? 'Actualité retirée de la vitrine (elle reste en brouillon).' : 'Actualité publiée sur la vitrine.';
    }

    public function ajouterSource(): void
    {
        $this->authorize('gerer-publications');
        $this->resetErrorBag();
        $this->validate(['fluxNom' => ['required', 'string', 'max:150'], 'fluxUrl' => ['required', 'string', 'max:500']], [], ['fluxNom' => 'nom', 'fluxUrl' => 'adresse']);

        try {
            RecuperationActualites::ajouterSource($this->fluxNom, $this->fluxUrl, $this->moi());
        } catch (OperationRefusee $e) {
            throw ValidationException::withMessages(['fluxUrl' => $e->getMessage()]);
        }

        $this->reset('fluxNom', 'fluxUrl');
        $this->statut = 'Source ajoutée. Cliquez sur « Récupérer » pour la lire.';
    }

    public function recupererSource(int $id): void
    {
        $this->authorize('gerer-publications');
        $source = SourceActualites::query()->findOrFail($id);
        try {
            $r = RecuperationActualites::recuperer($source, $this->moi());
            $this->statut = $r['nouveaux'].' nouvelle(s) actualité(s) en brouillon à relire ('.$r['ignores'].' déjà connue(s)).';
        } catch (OperationRefusee $e) {
            $this->statut = 'Récupération impossible : '.$e->getMessage();
        }
    }

    public function recupererTout(): void
    {
        $this->authorize('gerer-publications');
        $r = RecuperationActualites::toutesLesSources($this->moi());
        $this->statut = $r['nouveaux'].' nouvelle(s) actualité(s) en brouillon à relire'.($r['erreurs'] > 0 ? ' ; '.$r['erreurs'].' source(s) en erreur.' : '.');
    }

    public function basculerSource(int $id): void
    {
        $this->authorize('gerer-publications');
        $source = SourceActualites::query()->findOrFail($id);
        $source->update(['actif' => ! $source->actif]);
    }

    private function moi(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $produit = $this->produitId === '' ? null : Produit::query()->find((int) $this->produitId);

        return view('livewire.publications.gestion-publications', [
            'produits' => Produit::query()->where('actif', true)->orderBy('nom')->get(),
            'courants' => Publications::prixCourants(),
            'historique' => $produit === null ? collect() : Publications::historiquePrix($produit),
            'produitChoisi' => $produit,
            'sources' => SourceActualites::query()->orderBy('nom')->get(),
            'actualites' => Actualite::query()->orderByDesc('publie')->orderByDesc('publie_le')->orderByDesc('id')->get(),
        ]);
    }
}
