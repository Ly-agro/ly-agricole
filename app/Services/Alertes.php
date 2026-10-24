<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Enums\StatutDepense;
use App\Enums\StatutPret;
use App\Enums\StatutVente;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\Depense;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Vente;
use App\Support\Format;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alertes quotidiennes (réponse 52 : « tous les avis sont les bienvenus », à toute personne
 * ayant les permissions nécessaires). LECTURE SEULE sur les données des autres modules :
 * chaque calcul reprend celui du module qui en est propriétaire, sans le modifier.
 *
 * Une alerte ne part qu'une fois (table `alertes_envoyees`) : relancer la commande le
 * même jour ne renvoie rien. Titre et texte « push » génériques (écran verrouillé) : le
 * détail est dans l'application.
 */
class Alertes
{
    public const ATTENTE_HEURES = 48;

    /** @return array<string, int> nombre d'alertes envoyées par sorte */
    public static function executer(?Carbon $maintenant = null): array
    {
        $maintenant ??= now();

        return [
            'attente' => self::saisiesEnAttente($maintenant),
            'retard' => self::pretsEnRetard($maintenant),
            'budget' => self::budgetsDepasses(),
            'rapports' => self::alertesDesRapports($maintenant),
        ];
    }

    /** Saisies à valider depuis plus de 48 h : un rappel par personne et par jour. */
    private static function saisiesEnAttente(Carbon $maintenant): int
    {
        $limite = $maintenant->copy()->subHours(self::ATTENTE_HEURES);
        $sortes = [
            ['valider-achats', Achat::query()->where('statut', StatutAchat::AValider)->where('created_at', '<', $limite), 'achat', route('achats', ['statut' => 'a_valider'])],
            ['valider-depenses', Depense::query()->where('statut', StatutDepense::AValider)->where('created_at', '<', $limite), 'dépense', route('depenses')],
            ['valider-prets', Pret::query()->where('statut', StatutPret::Demande)->where('created_at', '<', $limite), 'prêt', route('prets')],
            ['valider-ventes', Vente::query()->where('statut', StatutVente::AValider)->where('created_at', '<', $limite), 'vente', route('ventes')],
        ];

        /** @var array<int, array{user: User, lignes: list<string>, url: string}> $parPersonne */
        $parPersonne = [];
        foreach ($sortes as [$droit, $requete, $nom, $url]) {
            // L'auteur ne valide pas sa propre saisie : on compte, pour chacun, ce qu'il PEUT valider.
            $saisies = $requete->get(['cree_par']);
            if ($saisies->isEmpty()) {
                continue;
            }
            foreach (Notifications::ayantLeDroit($droit) as $user) {
                $n = $saisies->where('cree_par', '!=', $user->id)->count();
                if ($n > 0) {
                    $parPersonne[$user->id] ??= ['user' => $user, 'lignes' => [], 'url' => $url];
                    $parPersonne[$user->id]['lignes'][] = $n.' '.$nom.($n > 1 ? 's' : '');
                }
            }
        }

        $envoyees = 0;
        foreach ($parPersonne as $id => $p) {
            if (self::premiereFois("attente:{$id}:".$maintenant->toDateString())) {
                Notifications::envoyer([$p['user']], 'Saisies en attente depuis plus de 2 jours',
                    implode(', ', $p['lignes']).' à valider.', $p['url'], 'a_valider',
                    implode(', ', $p['lignes']).' attendent votre validation.');
                $envoyees++;
            }
        }

        return $envoyees;
    }

    /**
     * Prêts en retard, selon la note de fiabilité (App\Services\FiabiliteProducteur :
     * décaissé, pas soldé, échéance dépassée) — la même définition partout.
     */
    private static function pretsEnRetard(Carbon $maintenant): int
    {
        $enRetard = 0;
        // Seuls les producteurs qui ont un prêt versé à échéance dépassée peuvent être en retard.
        $producteurs = Producteur::query()->whereKey(Pret::query()->where('statut', StatutPret::Decaisse)
            ->whereDate('echeance', '<', $maintenant->toDateString())->distinct()->pluck('producteur_id'))->get();
        foreach ($producteurs as $producteur) {
            $enRetard += FiabiliteProducteur::fiche($producteur, $maintenant)['synthese']['nb_en_retard'];
        }

        if ($enRetard === 0 || ! self::premiereFois('retard:'.$maintenant->toDateString())) {
            return 0;
        }
        $texte = $enRetard.' prêt'.($enRetard > 1 ? 's' : '').' en retard (échéance dépassée, pas soldé'.($enRetard > 1 ? 's' : '').').';
        Notifications::envoyer(Notifications::ayantLeDroit('voir-prets'), 'Prêts en retard', $texte, route('prets'), 'alerte', $texte);

        return 1;
    }

    /**
     * Postes de budget dépassés (App\Services\Budgets::suivi) sur les campagnes ouvertes :
     * une fois par poste et par montant prévu (un budget révisé réarme l'alerte).
     */
    private static function budgetsDepasses(): int
    {
        $envoyees = 0;
        foreach (Campagne::query()->where('statut', StatutCampagne::Ouverte)->get() as $campagne) {
            foreach (Budgets::suivi($campagne)['lignes'] as $l) {
                if ($l['ecart'] === null || $l['ecart'] >= 0 || ! self::premiereFois("budget:{$campagne->id}:{$l['cle']}:{$l['prevu']}")) {
                    continue;
                }
                Notifications::envoyer(Notifications::ayantLeDroit('voir-budget'), "Budget dépassé : {$l['libelle']}",
                    'Campagne '.$campagne->code.' : prévu '.Format::fcfa((int) $l['prevu']).', réel '.Format::fcfa($l['reel']).'.',
                    route('budget', ['campagne' => $campagne->id]), 'alerte', 'Un poste du budget de campagne est dépassé.');
                $envoyees++;
            }
        }

        return $envoyees;
    }

    /**
     * Écarts de poids et sauvegardes, tels que les liste le rapport « Alertes » de la
     * direction (App\Services\Rapports, semaine 10). Les prêts et saisies en attente de ce
     * rapport sont déjà couverts plus haut.
     */
    private static function alertesDesRapports(Carbon $maintenant): int
    {
        $retenues = collect((new Rapports)->alertes()->lignes)
            ->filter(fn (array $l) => $l[0] === 'Écart de poids' || str_starts_with((string) $l[0], 'Sauvegarde') || str_starts_with((string) $l[0], 'Copie hors site'));

        $envoyees = 0;
        foreach ($retenues as $l) {
            [$sorte, $objet, $detail] = [(string) $l[0], (string) $l[1], (string) $l[2]];
            // Écart : une fois par lot et par valeur ; sauvegardes : un rappel par jour.
            $cle = $sorte === 'Écart de poids' ? "ecart:{$objet}:".md5($detail) : 'rapport:'.md5($sorte).':'.$maintenant->toDateString();
            if (! self::premiereFois($cle)) {
                continue;
            }
            Notifications::envoyer(Notifications::ayantLeDroit('voir-rapports'), $sorte.($objet !== '—' ? " — {$objet}" : ''), $detail,
                route('rapports'), 'alerte', $sorte === 'Écart de poids' ? 'Un lot présente un écart de poids.' : 'Les sauvegardes demandent votre attention.');
            $envoyees++;
        }

        return $envoyees;
    }

    /** Vrai si la clé n'a jamais servi (et la marque) ; sûr même lancé deux fois en parallèle. */
    private static function premiereFois(string $cle): bool
    {
        try {
            DB::table('alertes_envoyees')->insert(['cle' => mb_substr($cle, 0, 191), 'envoye_at' => now()]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
