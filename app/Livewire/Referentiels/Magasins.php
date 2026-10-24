<?php

namespace App\Livewire\Referentiels;

use App\Models\Magasin;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends EcranReferentiel<Magasin>
 */
class Magasins extends EcranReferentiel
{
    protected function modele(): string
    {
        return Magasin::class;
    }

    protected function titre(): string
    {
        return 'Magasins';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Magasin '.$ligne->getAttribute('nom');
    }

    protected function requete(): Builder
    {
        return Magasin::query()->with('village')->orderBy('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'village_id' => ['libelle' => 'Village', 'type' => 'select', 'options' => OptionsVillages::toutes()],
            'capacite_kg' => ['libelle' => 'Capacité (kg)', 'type' => 'number', 'aide' => 'Facultatif. En kilos entiers.'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('magasins', 'nom')->ignore($existant)],
            'village_id' => ['required', 'integer', Rule::exists('villages', 'id')],
            // 10 millions de tonnes : borne de bon sens, pas une règle métier.
            'capacite_kg' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'actif' => ['boolean'],
        ];
    }

    protected function versModele(array $donnees): array
    {
        $donnees['capacite_g'] = $donnees['capacite_kg'] === null ? null : (int) $donnees['capacite_kg'] * 1000;
        unset($donnees['capacite_kg']);

        return $donnees;
    }

    protected function depuisModele(Model $ligne): array
    {
        /** @var Magasin $ligne */
        return [
            'nom' => $ligne->nom,
            'village_id' => $ligne->village_id,
            'capacite_kg' => $ligne->capacite_g === null ? null : intdiv($ligne->capacite_g, 1000),
            'actif' => $ligne->actif,
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Magasin $m) => $m->nom,
            'Village' => fn (Magasin $m) => $m->village->nom,
            'Capacité' => fn (Magasin $m) => Format::kg($m->capacite_g),
            'État' => fn (Magasin $m) => $m->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
