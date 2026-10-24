<?php

namespace App\Services\Ia;

use App\Exceptions\OperationRefusee;
use App\Models\FicheTraitement;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Référentiel des traitements (règles 1, 5 et 6 du skill ly-agricole-ia-conseil) : tenu
 * par l'agronome seul, daté, jamais rempli de mémoire. Une fiche ne se supprime pas : on la
 * retire ou on l'interdit (elle reste connue, pour que le contrôle rejette son nom).
 */
class Referentiel
{
    /**
     * @param  array{produit_id: int, type: string, cible: string, titre: string, description?: ?string, nom_commercial?: ?string, matiere_active?: ?string, dose?: ?string, passages?: ?int, delai_avant_recolte_jours?: ?int, toxicite_humaine?: ?string, protection?: ?string, effet_abeilles?: ?string, reference_homologation?: ?string, statut?: string}  $donnees
     */
    public static function enregistrer(array $donnees, User $agronome, ?FicheTraitement $fiche = null): FicheTraitement
    {
        if (! $agronome->can('gerer-referentiel-traitements')) {
            throw new OperationRefusee('Seul un agronome tient le référentiel des traitements.');
        }
        if (! array_key_exists($donnees['type'], FicheTraitement::TYPES)) {
            throw new OperationRefusee('Type de fiche inconnu.');
        }
        $statut = $donnees['statut'] ?? 'autorisee';
        if (! array_key_exists($statut, FicheTraitement::STATUTS)) {
            throw new OperationRefusee('Statut de fiche inconnu.');
        }

        $propres = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $donnees);
        // Une fiche non chimique n'a ni nom commercial ni dose : le modèle ne les verrait pas.
        if ($donnees['type'] !== 'chimique') {
            foreach (array_keys(FicheTraitement::CHAMPS_CHIMIQUE) as $cle) {
                $propres[$cle] = null;
            }
        }

        $valeurs = $propres + ['statut' => $statut];
        $valeurs['statut'] = $statut;
        // Chaque enregistrement par l'agronome date la validation (règle 6).
        $valeurs['validee_le'] = Carbon::today()->toDateString();
        $valeurs['validee_par'] = $agronome->id;

        if ($fiche === null) {
            return FicheTraitement::query()->create($valeurs);
        }
        $fiche->update($valeurs);

        return $fiche;
    }

    /** @return list<string> tous les noms et matières actives connus, retirés et interdits compris */
    public static function nomsConnus(): array
    {
        return FicheTraitement::query()->get(['nom_commercial', 'matiere_active'])
            ->flatMap(fn (FicheTraitement $f) => [$f->nom_commercial, $f->matiere_active])
            ->filter(fn ($n) => is_string($n) && $n !== '')->unique()->values()->all();
    }
}
