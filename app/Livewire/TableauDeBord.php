<?php

namespace App\Livewire;

use App\Models\Campagne;
use App\Services\Indicateurs;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page d'accueil : prix bord-champ, chiffres et graphiques de la campagne choisie,
 * actions à mener, dernières nouvelles. Chaque bloc n'apparaît qu'avec le droit
 * correspondant ; les chiffres viennent d'App\Services\Indicateurs.
 */
#[Title('Tableau de bord')]
class TableauDeBord extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $choixCampagne = '';

    public function render(Indicateurs $indicateurs): View
    {
        $user = auth()->user();
        $campagnes = $indicateurs->campagnes();
        $campagne = ctype_digit($this->choixCampagne)
            ? $campagnes->firstWhere('id', (int) $this->choixCampagne)
            : null;
        $campagne ??= $indicateurs->campagneParDefaut($campagnes);

        $voitPrets = $user->can('voir-prets');
        $voitArgent = $user->can('gerer-tresorerie');
        // Les totaux de dépenses sont réservés à ceux qui les valident : un agent les saisit, il ne voit pas le total.
        $voitDepenses = $user->can('valider-depenses');

        $voitFiliere = $user->can('saisir-achats') || $user->can('valider-achats');
        $voitStock = $user->can('gerer-stock');

        return view('livewire.tableau-de-bord.accueil', [
            'campagnes' => $campagnes,
            'campagne' => $campagne,
            'voitPrets' => $voitPrets,
            'voitArgent' => $voitArgent,
            'voitDepenses' => $voitDepenses,
            'chiffres' => $campagne && ($voitPrets || $voitDepenses) ? $indicateurs->chiffresCampagne($campagne) : null,
            'tresorerie' => $voitArgent ? $indicateurs->tresorerieTotale() : null,
            'flux' => $voitArgent ? $indicateurs->fluxMensuels() : [],
            'entrees' => $voitArgent ? $indicateurs->entreesParNature() : collect(),
            'depensesParCategorie' => $campagne && $voitDepenses ? $indicateurs->depensesParCategorie($campagne) : collect(),
            'pretsParStatut' => $campagne && $voitPrets ? $indicateurs->pretsParStatut($campagne) : collect(),
            'bilan' => $voitPrets || $voitDepenses ? $indicateurs->bilanParCampagne($campagnes) : collect(),
            'voitFiliere' => $voitFiliere,
            'voitStock' => $voitStock,
            'filiere' => $campagne && $voitFiliere ? $indicateurs->achatsEtStock($campagne) : null,
            'achatsParMois' => $campagne && $voitFiliere ? $indicateurs->achatsParMois($campagne) : collect(),
            'aFaire' => $indicateurs->aFaire($user),
            'actualite' => $indicateurs->actualite($user),
        ]);
    }
}
