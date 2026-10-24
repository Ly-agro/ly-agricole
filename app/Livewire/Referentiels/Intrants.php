<?php

namespace App\Livewire\Referentiels;

use App\Enums\UniteIntrant;
use App\Models\Intrant;
use App\Support\Format;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Fiches d'intrants. Le prix est le prix COURANT d'une unité : changer ce prix ne
 * modifie pas la valeur des remises déjà faites (figée à leur date).
 *
 * @extends EcranReferentiel<Intrant>
 */
class Intrants extends EcranReferentiel
{
    protected function droit(): string
    {
        return 'gerer-intrants';
    }

    protected function modele(): string
    {
        return Intrant::class;
    }

    protected function titre(): string
    {
        return 'Intrants';
    }

    protected function nomLigne(Model $ligne): string
    {
        return 'Intrant '.$ligne->getAttribute('nom');
    }

    protected function champs(): array
    {
        return [
            'nom' => ['libelle' => 'Nom et conditionnement', 'type' => 'text', 'aide' => 'ex. NPK 15-15-15 — sac de 50 kg'],
            'unite' => ['libelle' => 'Unité', 'type' => 'select',
                'options' => collect(UniteIntrant::cases())->mapWithKeys(fn (UniteIntrant $u) => [$u->value => ucfirst($u->libelle())])->all()],
            'prix_unitaire_fcfa' => ['libelle' => 'Prix d\'une unité (FCFA)', 'type' => 'text',
                'aide' => 'Prix courant. Les remises déjà faites gardent leur prix.'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'nom' => ['required', 'string', 'max:255', Rule::unique('intrants', 'nom')->ignore($existant)],
            'unite' => ['required', Rule::enum(UniteIntrant::class)],
            'prix_unitaire_fcfa' => ['required', Montant::regle()],
            'actif' => ['boolean'],
        ];
    }

    protected function versModele(array $donnees): array
    {
        $donnees['prix_unitaire_fcfa'] = (int) Montant::depuisSaisie((string) $donnees['prix_unitaire_fcfa']);

        return $donnees;
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Intrant $i) => $i->nom,
            'Unité' => fn (Intrant $i) => $i->unite->libelle(),
            'Prix courant' => fn (Intrant $i) => Format::fcfa($i->prix_unitaire_fcfa),
            'Stock total' => fn (Intrant $i) => $i->stock().' '.$i->unite->libelle($i->stock()),
            'État' => fn (Intrant $i) => $i->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
