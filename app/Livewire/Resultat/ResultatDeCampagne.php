<?php

namespace App\Livewire\Resultat;

use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Services\Apports;
use App\Services\PartageResultat;
use App\Services\ResultatCampagne;
use App\Support\Montant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Résultat net PROVISOIRE d'une campagne et son partage entre les investisseurs et LY
 * (contrat art. 10 à 14), pour la direction et la comptabilité. Rien n'est enregistré ni
 * communiqué : c'est un état de calcul. Les investisseurs ne voient encore aucun résultat.
 */
#[Title('Résultat de campagne')]
class ResultatDeCampagne extends Component
{
    #[Url(as: 'campagne', except: '')]
    public string $campagneId = '';

    /** Valeur du stock invendu (art. 11.3), donnée par la direction ; vide = 0. */
    public string $valeurStock = '';

    /** Art. 13.4 : décision de la direction, jamais déduite. Ne joue que sur une perte. */
    public bool $fauteLy = false;

    public function mount(): void
    {
        $this->authorize('voir-resultat-campagne');
        if ($this->campagneId === '') {
            $this->campagneId = (string) (Campagne::query()->orderByDesc('debut')->value('id') ?? '');
        }
    }

    public function render(): View
    {
        $campagne = Campagne::query()->with('produit')->find((int) $this->campagneId);
        $campagnes = Campagne::query()->with('produit')->orderByDesc('debut')->get();

        $stock = trim($this->valeurStock) === '' ? 0 : Montant::depuisSaisie($this->valeurStock);
        $erreurStock = $stock === null ? 'La valeur du stock doit être un nombre entier de FCFA, sans virgule ni point.' : null;

        $etat = null;
        $partage = null;
        $noms = collect();
        $refus = null;
        if ($campagne !== null) {
            $etat = ResultatCampagne::etat($campagne, $stock ?? 0);
            $repartition = Apports::repartition($campagne);
            $noms = $repartition['lignes']->mapWithKeys(fn (array $l) => [$l['investisseur']->id => $l['investisseur']->nom]);
            $investis = $repartition['lignes']->mapWithKeys(fn (array $l) => [$l['investisseur']->id => $l['montant']])->all();
            try {
                $partage = PartageResultat::calculer($etat['resultat_net'], $investis, $repartition['parLy'], $this->fauteLy);
            } catch (OperationRefusee $e) {
                $refus = $e->getMessage();
            }
        }

        return view('livewire.resultat.resultat-de-campagne', [
            'campagnes' => $campagnes,
            'campagne' => $campagne,
            'etat' => $etat,
            'partage' => $partage,
            'refus' => $refus,
            'erreurStock' => $erreurStock,
            'noms' => $noms,
        ]);
    }
}
