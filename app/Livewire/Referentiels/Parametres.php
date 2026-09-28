<?php

namespace App\Livewire\Referentiels;

use App\Enums\CleParametre;
use App\Models\Parametre;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Paramètres (seuils de validation…), réservés à la direction. La liste des clés vient
 * du code (CleParametre) ; aucune valeur par défaut : « non défini » reste visible.
 */
class Parametres extends Component
{
    /** Clé en cours de modification, ou null. */
    public ?string $edition = null;

    public string $valeur = '';

    public string $statut = '';

    #[Locked]
    public string $routePage = '';

    public function mount(): void
    {
        $this->authorize('gerer-parametres');
        $this->routePage = (string) request()->route()?->getName();
    }

    public function modifier(string $cle): void
    {
        $this->authorize('gerer-parametres');

        $cleParametre = CleParametre::from($cle);
        $this->resetErrorBag();
        $this->statut = '';
        $this->edition = $cleParametre->value;
        $this->valeur = (string) Parametre::entier($cleParametre);
    }

    public function annuler(): void
    {
        $this->resetErrorBag();
        $this->edition = null;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-parametres');

        $this->validate([
            'edition' => ['required', Rule::enum(CleParametre::class)],
            // Montants en FCFA entiers (D4). Vide = non défini.
            'valeur' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
        ], attributes: ['valeur' => 'valeur']);

        $cle = CleParametre::from((string) $this->edition);
        $valeur = $this->valeur === '' ? null : (string) (int) $this->valeur;

        $parametre = Parametre::query()->firstOrNew(['cle' => $cle]);
        $parametre->valeur = $valeur;
        $parametre->save();

        $this->statut = $cle->libelle().' : '.($valeur === null ? 'non défini.' : 'enregistré.');
        $this->edition = null;
    }

    public function render(): View
    {
        $valeurs = Parametre::query()->pluck('valeur', 'cle')->all();

        return view('livewire.referentiels.parametres', [
            'cles' => CleParametre::cases(),
            'valeurs' => $valeurs,
        ])->title('Paramètres');
    }
}
