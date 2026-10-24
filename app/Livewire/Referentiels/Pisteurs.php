<?php

namespace App\Livewire\Referentiels;

use App\Enums\ModeCommission;
use App\Models\Pisteur;
use App\Services\CommissionsPisteur;
use App\Support\Format;
use App\Support\Telephone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pisteurs qui vendent ce qu'ils ont collecté. Commission (question ouverte n° 6) : la règle de
 * chaque pisteur est saisie ici, VIDE tant que la direction ne l'a pas choisie ; le calcul
 * automatique se décide dans les Paramètres.
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
            'commission_mode' => ['libelle' => 'Commission — mode', 'type' => 'select', 'aide' => 'Facultatif : vide = pas de règle. Le calcul automatique se décide dans les Paramètres.',
                'options' => collect(ModeCommission::cases())->mapWithKeys(fn (ModeCommission $m) => [$m->value => $m->libelle()])->all()],
            'commission_valeur' => ['libelle' => 'Commission — valeur', 'type' => 'text', 'aide' => 'Entier : FCFA par kilo, ou pour mille du montant (20 = 2 %).'],
            'actif' => ['libelle' => 'Actif', 'type' => 'checkbox'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        $this->donnees['telephone'] = Telephone::normaliser($this->donnees['telephone'] ?? null);
        $this->donnees['commission_valeur'] = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) ($this->donnees['commission_valeur'] ?? '')) ?: null;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', Telephone::REGLE],
            'commission_mode' => ['nullable', Rule::enum(ModeCommission::class)],
            // Un entier positif ; en pour mille, jamais au-delà de 100 % (1 000 ‰).
            'commission_valeur' => ['nullable', 'integer', 'min:1', 'max:'.(($this->donnees['commission_mode'] ?? null) === ModeCommission::Pourcent->value ? 1000 : 1_000_000)],
            'actif' => ['boolean'],
        ];
    }

    protected function verifier(array $attributs, ?Model $existant): void
    {
        $mode = $attributs['commission_mode'] ?? null;
        $valeur = $attributs['commission_valeur'] ?? null;
        if (($mode === null) !== ($valeur === null)) {
            throw ValidationException::withMessages(['donnees.commission_valeur' => 'Le mode et la valeur de la commission vont ensemble : remplir les deux, ou aucun des deux.']);
        }
    }

    protected function colonnes(): array
    {
        return [
            'Nom' => fn (Pisteur $p) => $p->nom,
            'Téléphone' => fn (Pisteur $p) => Telephone::afficher($p->telephone),
            'Commission' => fn (Pisteur $p) => match ($p->commission_mode) {
                null => 'Non définie',
                ModeCommission::ParKg => Format::fcfa($p->commission_valeur).' / kg',
                ModeCommission::Pourcent => intdiv((int) $p->commission_valeur, 10).','.((int) $p->commission_valeur % 10).' %',
            },
            'Commission due' => fn (Pisteur $p) => Format::fcfa(CommissionsPisteur::due($p)),
            'État' => fn (Pisteur $p) => $p->actif ? 'Actif' : 'Désactivé',
        ];
    }
}
