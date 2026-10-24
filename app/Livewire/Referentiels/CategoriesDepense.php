<?php

namespace App\Livewire\Referentiels;

use App\Models\CategorieDepense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Catégories de dépense. « Exclue des fonds de campagne » = charge interdite sur le
 * compte d'une campagne (contrat, art. 10.3) ; la liste exacte est à fixer avec le
 * responsable projet.
 *
 * @extends EcranReferentiel<CategorieDepense>
 */
class CategoriesDepense extends EcranReferentiel
{
    protected function droit(): string
    {
        return 'gerer-tresorerie';
    }

    protected function modele(): string
    {
        return CategorieDepense::class;
    }

    protected function titre(): string
    {
        return 'Catégories de dépense';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Catégorie '.$ligne->getAttribute('nom');
    }

    protected function valeursInitiales(): array
    {
        return ['actif' => true, 'exclue_fonds_campagne' => false];
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'code_syscohada' => ['libelle' => 'Compte SYSCOHADA', 'type' => 'text', 'aide' => 'Facultatif (comptabilité, phase 3).'],
            'exclue_fonds_campagne' => ['libelle' => 'Exclue des fonds de campagne (art. 10.3)', 'type' => 'checkbox'],
            'actif' => ['libelle' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('categories_depense', 'nom')->ignore($existant)],
            'code_syscohada' => ['nullable', 'string', 'max:20'],
            'exclue_fonds_campagne' => ['boolean'],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (CategorieDepense $c) => $c->nom,
            'SYSCOHADA' => fn (CategorieDepense $c) => $c->code_syscohada ?? '—',
            'Fonds de campagne' => fn (CategorieDepense $c) => $c->exclue_fonds_campagne ? 'Exclue (art. 10.3)' : 'Autorisée',
            'État' => fn (CategorieDepense $c) => $c->actif ? 'Active' : 'Désactivée',
        ];
    }
}
