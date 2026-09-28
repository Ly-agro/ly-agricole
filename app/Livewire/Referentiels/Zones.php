<?php

namespace App\Livewire\Referentiels;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends EcranReferentiel<Zone>
 */
class Zones extends EcranReferentiel
{
    protected function modele(): string
    {
        return Zone::class;
    }

    protected function titre(): string
    {
        return 'Zones';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Zone '.$ligne->getAttribute('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text', 'aide' => 'Région ou département de collecte.'],
            'actif' => ['libelle' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('zones', 'nom')->ignore($existant)],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Zone $z) => $z->nom,
            'État' => fn (Zone $z) => $z->actif ? 'Active' : 'Désactivée',
        ];
    }
}
