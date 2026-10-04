<?php

namespace App\Services;

use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\User;
use App\Models\ValorisationStock;
use App\Support\Montant;
use Illuminate\Support\Carbon;

/**
 * Valorisation du stock invendu d'une campagne (contrat art. 11.3 ; question 32, décision
 * du responsable projet) : la direction saisit la valeur retenue **à partir de deux offres
 * de prix écrites**, et le logiciel garde les offres, leurs dates et fournisseurs, la valeur
 * retenue, l'auteur et la date. Il n'invente jamais une valeur de marché.
 *
 * Registre immuable : une nouvelle valorisation remplace la précédente (la plus récente fait
 * foi) et l'historique reste. Le contrat parle d'un prix « justifié par au moins deux offres
 * ou références de prix écrites » : deux fournisseurs DIFFÉRENTS sont exigés.
 */
class ValorisationsStock
{
    /**
     * @param  array{poids_g: int, offre1_fournisseur: string, offre1_date: Carbon, offre1_prix_kg_fcfa: int, offre2_fournisseur: string, offre2_date: Carbon, offre2_prix_kg_fcfa: int, valeur_retenue_fcfa: int, motif?: ?string}  $d
     */
    public static function enregistrer(Campagne $campagne, array $d, User $auteur): ValorisationStock
    {
        if (! $auteur->can('valoriser-stock')) {
            throw new OperationRefusee('Seule la direction valorise le stock invendu.');
        }

        $f1 = trim($d['offre1_fournisseur']);
        $f2 = trim($d['offre2_fournisseur']);
        if ($f1 === '' || $f2 === '') {
            throw new OperationRefusee('Chaque offre doit nommer son fournisseur.');
        }
        if (mb_strtolower($f1) === mb_strtolower($f2)) {
            throw new OperationRefusee('Il faut deux offres de deux fournisseurs différents (contrat, art. 11.3).');
        }
        foreach (['offre1', 'offre2'] as $offre) {
            if ($d["{$offre}_prix_kg_fcfa"] <= 0) {
                throw new OperationRefusee('Le prix au kilo de chaque offre doit être supérieur à zéro.');
            }
            if ($d["{$offre}_date"]->isFuture()) {
                throw new OperationRefusee('La date d\'une offre ne peut pas être dans le futur.');
            }
        }
        if ($d['poids_g'] <= 0) {
            throw new OperationRefusee('Le poids valorisé doit être supérieur à zéro.');
        }
        if ($d['valeur_retenue_fcfa'] < 0 || $d['valeur_retenue_fcfa'] > Montant::MAX) {
            throw new OperationRefusee('La valeur retenue doit être un montant en FCFA, positif ou nul.');
        }

        return ValorisationStock::query()->create([
            'campagne_id' => $campagne->id,
            'poids_g' => $d['poids_g'],
            'offre1_fournisseur' => $f1,
            'offre1_date' => $d['offre1_date']->toDateString(),
            'offre1_prix_kg_fcfa' => $d['offre1_prix_kg_fcfa'],
            'offre2_fournisseur' => $f2,
            'offre2_date' => $d['offre2_date']->toDateString(),
            'offre2_prix_kg_fcfa' => $d['offre2_prix_kg_fcfa'],
            'valeur_retenue_fcfa' => $d['valeur_retenue_fcfa'],
            'motif' => filled($d['motif'] ?? null) ? trim((string) $d['motif']) : null,
            'cree_par' => $auteur->id,
        ]);
    }

    /** La valorisation qui fait foi : la plus récente de la campagne. */
    public static function courante(Campagne $campagne): ?ValorisationStock
    {
        return ValorisationStock::query()->where('campagne_id', $campagne->id)->orderByDesc('id')->first();
    }

    /**
     * Valeur de marché que donnerait chacune des offres pour le poids valorisé (prix au kilo ×
     * kilos, arrondi au franc le plus proche), pour aider la direction à décider : ce n'est
     * PAS la valeur retenue.
     *
     * @return array{offre1: int, offre2: int}
     */
    public static function valeursDesOffres(ValorisationStock $v): array
    {
        $valeur = fn (int $prixKg) => intdiv($v->poids_g * $prixKg * 2 + 1000, 2000);

        return ['offre1' => $valeur($v->offre1_prix_kg_fcfa), 'offre2' => $valeur($v->offre2_prix_kg_fcfa)];
    }
}
