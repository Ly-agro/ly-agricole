<?php

namespace App\Livewire\Referentiels;

use App\Models\Village;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends EcranReferentiel<Village>
 */
class Villages extends EcranReferentiel
{
    protected function modele(): string
    {
        return Village::class;
    }

    protected function titre(): string
    {
        return 'Villages';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Village '.$ligne->getAttribute('nom');
    }

    protected function requete(): Builder
    {
        return Village::query()->with('zone')->orderBy('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'zone_id' => ['libelle' => 'Zone', 'type' => 'select', 'options' => Zone::query()->orderBy('nom')->get()
                ->mapWithKeys(fn (Zone $z) => [$z->id => $z->nom.($z->actif ? '' : ' (désactivée)')])->all()],
            'lat' => ['libelle' => 'Latitude', 'type' => 'number', 'aide' => 'Facultatif. En degrés décimaux, ex. 9.4580'],
            'lng' => ['libelle' => 'Longitude', 'type' => 'number', 'aide' => 'Facultatif. En degrés décimaux, ex. -5.6290'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255',
                Rule::unique('villages', 'nom')->where('zone_id', $this->donnees['zone_id'] ?? null)->ignore($existant)],
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Village $v) => $v->nom,
            'Zone' => fn (Village $v) => $v->zone->nom,
            'GPS' => fn (Village $v) => $v->lat !== null && $v->lng !== null ? $v->lat.', '.$v->lng : '—',
            'État' => fn (Village $v) => $v->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
