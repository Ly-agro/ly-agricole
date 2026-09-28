<?php

namespace App\Livewire\Referentiels;

use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\Produit;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Campagnes : réservées à la direction (elle fixe les prix, cahier §2).
 *
 * Règles : une seule campagne ouverte par produit (l'appli terrain télécharge « la »
 * campagne ouverte) ; le produit ne change plus une fois la campagne ouverte ; une
 * campagne clôturée ne se modifie plus. Le prix officiel peut rester vide tant qu'il
 * n'est pas annoncé. La clôture viendra avec le calcul du résultat (D6).
 *
 * @extends EcranReferentiel<Campagne>
 */
class Campagnes extends EcranReferentiel
{
    protected function droit(): string
    {
        return 'gerer-campagnes';
    }

    protected function modele(): string
    {
        return Campagne::class;
    }

    protected function titre(): string
    {
        return 'Campagnes';
    }

    protected function nomLigne(Model $ligne): string
    {
        /** @var Campagne $ligne */
        return 'Campagne '.$ligne->produit->nom.' '.$ligne->code;
    }

    protected function requete(): Builder
    {
        return Campagne::query()->with('produit')->orderByDesc('debut');
    }

    protected function valeursInitiales(): array
    {
        return [];
    }

    protected function champs(): array
    {
        return [
            'produit_id' => ['libelle' => 'Produit', 'type' => 'select', 'options' => Produit::query()->orderBy('nom')->get()
                ->mapWithKeys(fn (Produit $p) => [$p->id => $p->nom.($p->actif ? '' : ' (désactivé)')])->all()],
            'code' => ['libelle' => 'Code', 'type' => 'text', 'aide' => 'Années de la campagne, ex. 2026-2027.'],
            'debut' => ['libelle' => 'Début', 'type' => 'date'],
            'fin' => ['libelle' => 'Fin', 'type' => 'date'],
            'prix_officiel_kg_fcfa' => ['libelle' => 'Prix officiel bord-champ (FCFA/kg)', 'type' => 'number',
                'aide' => 'Laisser vide tant que le prix n\'est pas annoncé. FCFA entiers.'],
        ];
    }

    protected function regles(?Model $existant): array
    {
        return [
            'produit_id' => ['required', 'integer', Rule::exists('produits', 'id')],
            'code' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('campagnes', 'code')->where('produit_id', $this->donnees['produit_id'] ?? null)->ignore($existant)],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:donnees.debut'],
            // FCFA entiers (D4) ; la borne haute évite une faute de frappe à 9 chiffres.
            'prix_officiel_kg_fcfa' => ['nullable', 'integer', 'min:1', 'max:99999999'],
        ];
    }

    protected function versModele(array $donnees): array
    {
        $donnees['prix_officiel_kg_fcfa'] = $donnees['prix_officiel_kg_fcfa'] === null ? null : (int) $donnees['prix_officiel_kg_fcfa'];

        return $donnees;
    }

    protected function depuisModele(Model $ligne): array
    {
        /** @var Campagne $ligne */
        return [
            'produit_id' => $ligne->produit_id,
            'code' => $ligne->code,
            'debut' => $ligne->debut->format('Y-m-d'),
            'fin' => $ligne->fin->format('Y-m-d'),
            'prix_officiel_kg_fcfa' => $ligne->prix_officiel_kg_fcfa,
        ];
    }

    protected function verifier(array $attributs, ?Model $existant): void
    {
        /** @var Campagne|null $existant */
        if ($existant && $existant->statut !== StatutCampagne::Preparation
            && (int) $attributs['produit_id'] !== $existant->produit_id) {
            throw ValidationException::withMessages([
                'donnees.produit_id' => 'Le produit d\'une campagne ouverte ne peut plus changer.',
            ]);
        }
    }

    protected function peutModifier(Model $ligne): bool
    {
        /** @var Campagne $ligne */
        return $ligne->statut !== StatutCampagne::Cloturee;
    }

    protected function actionsLigne(Model $ligne): array
    {
        /** @var Campagne $ligne */
        return $ligne->statut === StatutCampagne::Preparation ? ['ouvrir' => 'Ouvrir'] : [];
    }

    public function ouvrir(int $id): void
    {
        $this->authorize($this->droit());
        $this->resetErrorBag();
        $this->statut = '';

        DB::transaction(function () use ($id) {
            $campagne = Campagne::query()->lockForUpdate()->findOrFail($id);

            if ($campagne->statut !== StatutCampagne::Preparation) {
                throw ValidationException::withMessages(['ligne' => 'Seule une campagne en préparation peut être ouverte.']);
            }

            $dejaOuverte = Campagne::query()
                ->where('produit_id', $campagne->produit_id)
                ->where('statut', StatutCampagne::Ouverte)
                ->first();

            if ($dejaOuverte) {
                throw ValidationException::withMessages([
                    'ligne' => "La campagne {$dejaOuverte->code} est déjà ouverte pour ce produit : une seule à la fois.",
                ]);
            }

            $campagne->update(['statut' => StatutCampagne::Ouverte]);
            $this->statut = $this->nomLigne($campagne).' : ouverte.';
        });
    }

    protected function colonnes(): array
    {
        return [
            'Produit' => fn (Campagne $c) => $c->produit->nom,
            'Code' => fn (Campagne $c) => $c->code,
            'Période' => fn (Campagne $c) => $c->debut->format('d/m/Y').' → '.$c->fin->format('d/m/Y'),
            'Prix officiel' => fn (Campagne $c) => $c->prix_officiel_kg_fcfa === null ? 'Non annoncé' : Format::fcfa($c->prix_officiel_kg_fcfa).'/kg',
            'Statut' => fn (Campagne $c) => $c->statut->libelle(),
        ];
    }
}
