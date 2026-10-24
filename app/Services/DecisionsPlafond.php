<?php

namespace App\Services;

use App\Enums\CleParametre;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\DecisionPlafond;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Décision de la direction sur le plafond de prêt d'un producteur (question 35, décision du
 * responsable projet) : le logiciel PROPOSE (FiabiliteProducteur), la direction DÉCIDE, et on
 * garde tout : la proposition, sa raison, les données utilisées, la date du calcul, le plafond
 * retenu, le motif et l'auteur.
 *
 * L'instantané est recalculé ICI, côté serveur, au moment de la décision : jamais repris d'un
 * formulaire. Décider autre chose que la proposition (ou décider un plafond quand rien n'est
 * proposé) exige un motif. Le plafond retenu ne peut pas dépasser le plafond par producteur des
 * Paramètres (celui-là est appliqué à chaque prêt : deux règles contradictoires n'ont pas de sens).
 * Ce plafond n'est pas appliqué automatiquement à la création d'un prêt : c'est une trace.
 */
class DecisionsPlafond
{
    public static function enregistrer(Producteur $producteur, int $plafondRetenu, ?string $motif, ?Campagne $campagne, User $auteur): DecisionPlafond
    {
        if (! $auteur->can('decider-plafond')) {
            throw new OperationRefusee('Seule la direction décide du plafond d\'un producteur.');
        }
        if ($plafondRetenu < 0 || $plafondRetenu > Montant::MAX) {
            throw new OperationRefusee('Le plafond retenu doit être un montant en FCFA, positif ou nul.');
        }

        $fiche = FiabiliteProducteur::fiche($producteur);
        $propose = $fiche['plafond_propose'];
        $motif = filled($motif) ? trim((string) $motif) : null;

        if ($propose !== $plafondRetenu && ($motif === null || mb_strlen($motif) < 5)) {
            throw new OperationRefusee($propose === null
                ? 'Aucun plafond n\'est proposé : un motif (5 caractères au moins) est obligatoire pour en fixer un.'
                : 'Le plafond retenu diffère de la proposition : un motif (5 caractères au moins) est obligatoire.');
        }

        $limite = Parametre::entier(CleParametre::PlafondPretProducteur);
        if ($limite !== null && $plafondRetenu > $limite) {
            throw new OperationRefusee('Le plafond retenu dépasse le plafond par producteur des Paramètres ('.number_format($limite, 0, ',', ' ').' FCFA).');
        }

        return DecisionPlafond::query()->create([
            'producteur_id' => $producteur->id,
            'campagne_id' => $campagne?->id,
            'plafond_propose_fcfa' => $propose,
            'raison_proposition' => $fiche['raison'],
            'plafond_retenu_fcfa' => $plafondRetenu,
            'motif' => $motif,
            'donnees' => [
                'synthese' => $fiche['synthese'],
                'prets' => array_map(fn (array $p) => [
                    'reference' => $p['pret']->reference,
                    'campagne' => $p['pret']->campagne->code,
                    'echeance' => $p['pret']->echeance->toDateString(),
                    'remis_fcfa' => $p['remis'],
                    'rembourse_fcfa' => $p['rembourse'],
                    'solde' => $p['solde'],
                    'a_temps' => $p['a_temps'],
                    'retard_jours' => $p['retard_jours'],
                ], $fiche['prets']),
            ],
            'calcule_at' => Carbon::now(),
            'cree_par' => $auteur->id,
        ]);
    }

    /** @return Collection<int, DecisionPlafond> La plus récente d'abord. */
    public static function historique(Producteur $producteur): Collection
    {
        return DecisionPlafond::query()->with(['auteur', 'campagne.produit'])->where('producteur_id', $producteur->id)->orderByDesc('id')->get();
    }
}
