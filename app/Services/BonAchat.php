<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Models\Achat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Bon d'achat bord-champ à faire signer par le fournisseur (cahier §7) : pesée, qualité,
 * prix, kilos retenus pour le prêt, somme payée. Un achat à valider ou refusé l'écrit en
 * gros : ce bon ne prouve alors aucun paiement.
 */
class BonAchat
{
    public static function pdf(Achat $achat): Response
    {
        $achat->loadMissing('producteur.village', 'pisteur', 'lot', 'campagne.produit', 'pret', 'auteur', 'validateur');

        $mention = match ($achat->statut) {
            StatutAchat::AValider => 'EN ATTENTE DE VALIDATION — rien n\'est encore payé ni retenu.',
            StatutAchat::Refuse => 'ACHAT REFUSÉ — ce bon n\'a aucune valeur de paiement.',
            StatutAchat::Valide => null,
        };

        return Pdf::loadView('achats.bon-achat', [
            'achat' => $achat,
            'mention' => $mention,
            'valeurRetenue' => $achat->montant_fcfa - $achat->montant_especes_fcfa,
            'restantDu' => $achat->pret?->restantDu(),
        ])->setPaper('a5')->setOption('isFontSubsettingEnabled', true)->stream('bon-'.$achat->reference.'.pdf');
    }
}
