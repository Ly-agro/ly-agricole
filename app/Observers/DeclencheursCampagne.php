<?php

namespace App\Observers;

use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Services\Notifications;
use App\Support\Format;

/**
 * Campagne ouverte, prix officiel annoncé ou changé : ceux qui achètent doivent le savoir
 * tout de suite (le prix bord-champ est le plancher de chaque achat). Lecture seule.
 * Le prix officiel est public : il peut figurer dans le push.
 */
class DeclencheursCampagne
{
    // Pas de `created` : une campagne naît « en préparation » et s'ouvre par l'action
    // « Ouvrir » (écran Campagnes) — c'est ce passage qui prévient.
    public function updated(Campagne $campagne): void
    {
        if ($campagne->wasChanged('statut') && $campagne->statut === StatutCampagne::Ouverte) {
            $this->ouverte($campagne);

            return;
        }

        if ($campagne->wasChanged('prix_officiel_kg_fcfa') && $campagne->prix_officiel_kg_fcfa !== null && $campagne->statut === StatutCampagne::Ouverte) {
            $avant = $campagne->getOriginal('prix_officiel_kg_fcfa');
            $texte = 'Prix officiel bord-champ : '.Format::fcfa($campagne->prix_officiel_kg_fcfa).'/kg'
                .($avant === null ? '.' : ' (avant : '.Format::fcfa((int) $avant).'/kg).');
            Notifications::envoyer(Notifications::ayantLeDroit('saisir-achats'),
                'Prix officiel '.$this->nom($campagne), $texte, route('tableau-de-bord'), 'alerte', $texte);
        }
    }

    private function ouverte(Campagne $campagne): void
    {
        $prix = $campagne->prix_officiel_kg_fcfa === null ? 'prix officiel pas encore annoncé' : 'prix officiel '.Format::fcfa($campagne->prix_officiel_kg_fcfa).'/kg';
        $texte = 'La campagne est ouverte ('.$prix.'). Télécharger les référentiels sur le téléphone.';
        Notifications::envoyer(Notifications::ayantLeDroit('saisir-achats'),
            'Campagne '.$this->nom($campagne).' ouverte', $texte, route('tableau-de-bord'), 'alerte', $texte);
    }

    private function nom(Campagne $campagne): string
    {
        return ($campagne->produit->nom ?? 'Produit').' '.$campagne->code;
    }
}
