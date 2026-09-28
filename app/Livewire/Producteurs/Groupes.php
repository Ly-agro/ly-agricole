<?php

namespace App\Livewire\Producteurs;

use App\Livewire\Referentiels\EcranReferentiel;
use App\Livewire\Referentiels\OptionsVillages;
use App\Models\GroupeProducteur;
use App\Models\Producteur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Groupes de producteurs d'un village (caution solidaire en phase 2). Le responsable
 * est un producteur du même village.
 *
 * @extends EcranReferentiel<GroupeProducteur>
 */
class Groupes extends EcranReferentiel
{
    protected function droit(): string
    {
        return 'gerer-producteurs';
    }

    protected function afficherOnglets(): bool
    {
        return false;
    }

    protected function modele(): string
    {
        return GroupeProducteur::class;
    }

    protected function titre(): string
    {
        return 'Groupes de producteurs';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Groupe '.$ligne->getAttribute('nom');
    }

    protected function requete(): Builder
    {
        return GroupeProducteur::query()->with('village.zone', 'responsable')->withCount('membres')->orderBy('nom');
    }

    protected function champs(): array
    {
        $villageId = $this->donnees['village_id'] ?? null;

        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'village_id' => ['libelle' => 'Village', 'type' => 'select', 'options' => OptionsVillages::toutes(), 'live' => true],
            'responsable_id' => ['libelle' => 'Responsable', 'type' => 'select', 'aide' => 'Facultatif. Un producteur du village.',
                'options' => blank($villageId) ? [] : Producteur::query()->where('village_id', (int) $villageId)->where('actif', true)
                    ->orderBy('nom')->get()->mapWithKeys(fn (Producteur $p) => [$p->id => $p->nomComplet().' ('.$p->code.')'])->all()],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        $villageId = $this->donnees['village_id'] ?? null;

        return [
            'nom' => ['required', 'string', 'max:255',
                Rule::unique('groupes_producteurs', 'nom')->where('village_id', $villageId)->ignore($existant)],
            'village_id' => ['required', 'integer', Rule::exists('villages', 'id')],
            'responsable_id' => ['nullable', 'uuid', Rule::exists('producteurs', 'id')->where('village_id', $villageId)],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (GroupeProducteur $g) => $g->nom,
            'Village' => fn (GroupeProducteur $g) => $g->village->nom.' ('.$g->village->zone->nom.')',
            'Responsable' => fn (GroupeProducteur $g) => $g->responsable?->nomComplet() ?? '—',
            'Membres' => fn (GroupeProducteur $g) => (string) $g->getAttribute('membres_count'),
            'État' => fn (GroupeProducteur $g) => $g->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
