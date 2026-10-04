<?php

namespace App\Models;

use App\Enums\CalculCommissionPisteur;
use App\Enums\CleParametre;
use App\Enums\RegleCautionSolidaire;
use App\Enums\RegleValorisationNature;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage modifiable par la direction (seuils…). Lire via `Parametre::entier()`.
 *
 * @property int $id
 * @property CleParametre $cle
 * @property string|null $valeur
 */
#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    use Journalise;

    /**
     * Valeur entière du paramètre, ou null s'il n'est pas défini. Null ne veut PAS
     * dire zéro : à l'appelant d'appliquer la règle prudente (voir CleParametre).
     */
    public static function entier(CleParametre $cle): ?int
    {
        $valeur = static::query()->where('cle', $cle)->value('valeur');

        return $valeur === null || $valeur === '' ? null : (int) $valeur;
    }

    /** Règle de valorisation choisie par la direction, ou null si elle n'a pas choisi. */
    public static function regleRemboursementNature(): ?RegleValorisationNature
    {
        $valeur = static::query()->where('cle', CleParametre::RegleRemboursementNature)->value('valeur');

        return RegleValorisationNature::tryFrom((string) $valeur);
    }

    /** Règle de caution solidaire choisie ; « aucune » tant que la direction n'a rien choisi. */
    public static function regleCautionSolidaire(): RegleCautionSolidaire
    {
        $valeur = static::query()->where('cle', CleParametre::CautionSolidaire)->value('valeur');

        return RegleCautionSolidaire::tryFrom((string) $valeur) ?? RegleCautionSolidaire::Aucune;
    }

    /** Calcul automatique des commissions de pisteurs ; « aucun » tant que la direction n'a rien choisi. */
    public static function calculCommissionPisteur(): CalculCommissionPisteur
    {
        $valeur = static::query()->where('cle', CleParametre::CalculCommissionPisteur)->value('valeur');

        return CalculCommissionPisteur::tryFrom((string) $valeur) ?? CalculCommissionPisteur::Aucun;
    }

    protected function casts(): array
    {
        return ['cle' => CleParametre::class];
    }
}
