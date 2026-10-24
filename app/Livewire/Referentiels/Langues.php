<?php

namespace App\Livewire\Referentiels;

use App\Models\Langue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Langues de préférence des producteurs pour les messages (question 12). Le français est la
 * langue par défaut d'un producteur qui n'en a pas : il n'est pas dans cette liste. Aucune
 * langue locale n'est pré-remplie ; la colonne « Producteurs » aide à décider lesquelles
 * traduire en premier.
 *
 * @extends EcranReferentiel<Langue>
 */
class Langues extends EcranReferentiel
{
    protected function modele(): string
    {
        return Langue::class;
    }

    protected function titre(): string
    {
        return 'Langues des messages';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Langue '.$ligne->getAttribute('nom');
    }

    protected function requete(): Builder
    {
        return Langue::query()->withCount('producteurs')->orderBy('nom');
    }

    protected function champs(): array
    {
        return [
            'code' => ['libelle' => 'Code', 'type' => 'text', 'aide' => 'Court, en minuscules (ex. « dyu »). Ne change plus après.'],
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'actif' => ['libelle' => 'Active', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        $this->donnees['code'] = mb_strtolower(trim((string) ($this->donnees['code'] ?? '')));

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('langues', 'code')->ignore($existant?->getKey())],
            'nom' => ['required', 'string', 'max:255'],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Langue $l) => $l->nom,
            'Code' => fn (Langue $l) => $l->code,
            'Producteurs' => fn (Langue $l) => (string) $l->getAttribute('producteurs_count'),
            'État' => fn (Langue $l) => $l->actif ? 'Active' : 'Désactivée',
        ];
    }
}
