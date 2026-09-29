<?php

namespace App\Livewire\Referentiels;

use App\Models\Pisteur;
use App\Support\Telephone;
use Illuminate\Database\Eloquent\Model;

/**
 * Pisteurs qui vendent ce qu'ils ont collecté. Commission : question ouverte n° 6.
 *
 * @extends EcranReferentiel<Pisteur>
 */
class Pisteurs extends EcranReferentiel
{
    protected function modele(): string
    {
        return Pisteur::class;
    }

    protected function titre(): string
    {
        return 'Pisteurs';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Pisteur '.$ligne->getAttribute('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom', 'type' => 'text'],
            'telephone' => ['libelle' => 'Téléphone', 'type' => 'text', 'aide' => '10 chiffres, facultatif.'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        $this->donnees['telephone'] = Telephone::normaliser($this->donnees['telephone'] ?? null);

        return [
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', Telephone::REGLE],
            'actif' => ['boolean'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Pisteur $p) => $p->nom,
            'Téléphone' => fn (Pisteur $p) => Telephone::afficher($p->telephone),
            'État' => fn (Pisteur $p) => $p->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
