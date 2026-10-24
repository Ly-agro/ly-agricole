<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Models\Achat;
use App\Models\Decaissement;
use App\Models\MouvementIntrant;
use App\Models\Pret;
use App\Support\Format;
use App\Support\Ticket58;

/**
 * Tickets 58 mm des mêmes pièces que les PDF A5 (App\Services\BonAchat, RecuRemise) :
 * bon d'achat et reçu de remise d'un prêt. Mêmes informations, en 32 colonnes. Ces
 * classes-là ne sont pas modifiées : on lit les mêmes modèles.
 */
class Tickets
{
    public static function achat(Achat $achat): Ticket58
    {
        $achat->loadMissing('producteur.village', 'pisteur', 'lot', 'campagne.produit', 'pret', 'auteur');
        $t = self::entete("Bon d'achat", $achat->reference, $achat->date_achat->format('d/m/Y H:i'));

        match ($achat->statut) {
            StatutAchat::AValider => $t->trait('*')->centre('EN ATTENTE DE VALIDATION', true)->centre("Rien n'est encore payé ni retenu.")->trait('*'),
            StatutAchat::Refuse => $t->trait('*')->centre('ACHAT REFUSÉ', true)->centre("Ce bon n'a aucune valeur de paiement.")->trait('*'),
            StatutAchat::Valide => null,
        };

        $t->texte('Fournisseur : '.$achat->nomFournisseur(), true);
        if ($achat->producteur) {
            $t->texte('Carte '.$achat->producteur->code.' — '.$achat->producteur->village->nom);
        }
        $t->texte($achat->campagne->produit->nom.' — campagne '.$achat->campagne->code)
            ->texte('Lot '.$achat->lot->code)
            ->texte('Acheteur : '.$achat->auteur->nom)
            ->trait()
            ->paire('Poids brut', Format::kg($achat->poids_brut_g))
            ->paire('Tare (sacs)', Format::kg($achat->tare_g))
            ->paire('Poids net', Format::kg($achat->poids_net_g), true);

        if ($achat->humidite_pour_mille !== null) {
            $t->paire('Humidité', intdiv($achat->humidite_pour_mille, 10).','.($achat->humidite_pour_mille % 10).' %');
        }
        if ($achat->kor_centieme_lbs !== null) {
            $t->paire('KOR (lbs/80 kg)', intdiv($achat->kor_centieme_lbs, 100).','.str_pad((string) ($achat->kor_centieme_lbs % 100), 2, '0', STR_PAD_LEFT));
        }
        if ($achat->grainage_noix_kg !== null) {
            $t->paire('Grainage (noix/kg)', (string) $achat->grainage_noix_kg);
        }

        $t->trait()
            ->paire('Prix', Format::fcfa($achat->prix_kg_fcfa).'/kg')
            ->paire('Montant', Format::fcfa($achat->montant_fcfa), true);
        if ($achat->pret) {
            $t->paire('Retenu prêt '.$achat->pret->reference.' ('.Format::kg($achat->grammes_rembourses).')', '- '.Format::fcfa($achat->montant_fcfa - $achat->montant_especes_fcfa));
        }
        $t->paire($achat->statut === StatutAchat::Valide ? 'PAYÉ EN ESPÈCES' : 'À PAYER', Format::fcfa($achat->montant_especes_fcfa), true);

        if ($achat->pret) {
            $t->trait()->texte('Restant dû sur le prêt au '.now()->format('d/m/Y').' : '.Format::fcfa($achat->pret->restantDu()));
        }

        return $t->trait()
            ->texte('Le fournisseur reconnaît avoir livré le poids net ci-dessus et reçu la somme payée en espèces.')
            ->signature('Fournisseur')
            ->signature('Acheteur')
            ->vide();
    }

    public static function remiseArgent(Pret $pret, Decaissement $decaissement): Ticket58
    {
        abort_unless($decaissement->pret_id === $pret->id, 404);

        return self::remise($pret, 'R-'.$pret->reference.'-A'.$decaissement->id, $decaissement->date_decaissement->format('d/m/Y'),
            'Argent ('.$decaissement->mode->libelle().')'.($decaissement->reference_paiement ? ', réf. '.$decaissement->reference_paiement : ''),
            null, $decaissement->montant_fcfa);
    }

    public static function remiseIntrants(Pret $pret, MouvementIntrant $mouvement): Ticket58
    {
        abort_unless($mouvement->pret_id === $pret->id && $mouvement->type->value === 'distribution', 404);
        $mouvement->loadMissing('intrant');

        return self::remise($pret, 'R-'.$pret->reference.'-I'.$mouvement->id, $mouvement->date_mouvement->format('d/m/Y'),
            $mouvement->intrant->nom,
            -$mouvement->quantite.' '.$mouvement->intrant->unite->libelle(-$mouvement->quantite).' × '.Format::fcfa((int) $mouvement->prix_unitaire_fcfa),
            -(int) $mouvement->valeur_fcfa);
    }

    private static function remise(Pret $pret, string $numero, string $date, string $libelle, ?string $detail, int $valeur): Ticket58
    {
        $pret->loadMissing('producteur.village', 'campagne.produit');
        $t = self::entete('Reçu de remise', $numero, $date)
            ->texte('Producteur : '.$pret->producteur->nomComplet(), true)
            ->texte('Carte '.$pret->producteur->code.' — '.$pret->producteur->village->nom)
            ->texte('Prêt '.$pret->reference.' — '.$pret->campagne->produit->nom.' '.$pret->campagne->code)
            ->trait()
            ->texte($libelle);
        if ($detail !== null) {
            $t->texte($detail);
        }

        return $t->paire('Valeur remise', Format::fcfa($valeur), true)
            ->trait()
            ->paire('Total remis sur le prêt', Format::fcfa($pret->montantRemis()))
            ->paire('RESTANT DÛ', Format::fcfa($pret->restantDu()), true)
            ->trait()
            ->texte('Le producteur reconnaît avoir reçu ce qui est écrit ci-dessus, au titre du prêt de campagne.')
            ->signature('Producteur')
            ->signature('Agent')
            ->vide();
    }

    private static function entete(string $piece, string $numero, string $date): Ticket58
    {
        return (new Ticket58)
            ->titre('LY AGRICOLE')
            ->centre('Cultiver – Élever – Durer')
            ->trait('=')
            ->centre(mb_strtoupper($piece), true)
            ->centre('N° '.$numero)
            ->centre('du '.$date)
            ->trait('=');
    }
}
