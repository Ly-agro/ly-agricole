<?php

namespace App\Livewire\Referentiels;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends EcranReferentiel<Produit>
 */
class Produits extends EcranReferentiel
{
    protected function modele(): string
    {
        return Produit::class;
    }

    protected function titre(): string
    {
        return 'Produits';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Produit '.$ligne->getAttribute('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text', 'aide' => 'Ex. Anacarde, Karité, Tomate.'],
            'code' => ['libelle' => 'Code', 'type' => 'text', 'aide' => 'Minuscules sans accents ni espaces, ex. anacarde. Ne plus le changer une fois utilisé.'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('produits', 'code')->ignore($existant)],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Produit $p) => $p->nom,
            'Code' => fn (Produit $p) => $p->code,
            'État' => fn (Produit $p) => $p->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
