<?php

namespace App\Livewire\Referentiels;

use App\Models\PointCollecte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends EcranReferentiel<PointCollecte>
 */
class PointsCollecte extends EcranReferentiel
{
    protected function modele(): string
    {
        return PointCollecte::class;
    }

    protected function titre(): string
    {
        return 'Points de collecte';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Point de collecte '.$ligne->getAttribute('nom');
    }

    protected function requete(): Builder
    {
        return PointCollecte::query()->with('village.zone')->orderBy('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'village_id' => ['libelle' => 'Village', 'type' => 'select', 'options' => OptionsVillages::toutes()],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255',
                Rule::unique('points_collecte', 'nom')->where('village_id', $this->donnees['village_id'] ?? null)->ignore($existant)],
            'village_id' => ['required', 'integer', Rule::exists('villages', 'id')],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (PointCollecte $p) => $p->nom,
            'Village' => fn (PointCollecte $p) => $p->village->nom.' ('.$p->village->zone->nom.')',
            'État' => fn (PointCollecte $p) => $p->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
