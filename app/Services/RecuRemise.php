<?php

namespace App\Services;

use App\Models\Decaissement;
use App\Models\MouvementIntrant;
use App\Models\Pret;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Reçu d'une remise au producteur (argent ou intrants à crédit), à faire signer.
 * Il montre ce qui est remis ce jour-là et, à la date d'impression, le total remis et
 * le restant dû du prêt : ce que le producteur doit, écrit noir sur blanc.
 */
class RecuRemise
{
    public static function argent(Pret $pret, Decaissement $decaissement): Response
    {
        abort_unless($decaissement->pret_id === $pret->id, 404);

        return self::rendre($pret, 'R-'.$pret->reference.'-A'.$decaissement->id, $decaissement->date_decaissement->format('d/m/Y'), [[
            'libelle' => 'Argent ('.$decaissement->mode->libelle().')'.($decaissement->reference_paiement ? ', réf. '.$decaissement->reference_paiement : ''),
            'quantite' => null,
            'prix' => null,
            'valeur' => $decaissement->montant_fcfa,
        ]]);
    }

    public static function intrants(Pret $pret, MouvementIntrant $mouvement): Response
    {
        abort_unless($mouvement->pret_id === $pret->id && $mouvement->type->value === 'distribution', 404);
        $mouvement->loadMissing('intrant');

        return self::rendre($pret, 'R-'.$pret->reference.'-I'.$mouvement->id, $mouvement->date_mouvement->format('d/m/Y'), [[
            'libelle' => $mouvement->intrant->nom,
            'quantite' => -$mouvement->quantite.' '.$mouvement->intrant->unite->libelle(-$mouvement->quantite),
            'prix' => (int) $mouvement->prix_unitaire_fcfa,
            'valeur' => -(int) $mouvement->valeur_fcfa,
        ]]);
    }

    /**
     * @param  list<array{libelle: string, quantite: ?string, prix: ?int, valeur: int}>  $lignes
     */
    private static function rendre(Pret $pret, string $numero, string $date, array $lignes): Response
    {
        $pret->loadMissing('producteur.village', 'campagne.produit');

        return Pdf::loadView('prets.recu-remise', [
            'pret' => $pret,
            'numero' => $numero,
            'date' => $date,
            'lignes' => $lignes,
            'remis' => $pret->montantRemis(),
            'restantDu' => $pret->restantDu(),
        ])->setPaper('a5')->setOption('isFontSubsettingEnabled', true)->stream($numero.'.pdf');
    }
}
