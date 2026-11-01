<?php

namespace App\Services;

use App\Exceptions\OperationRefusee;
use App\Models\CompteTresorerie;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GestionComptesTresorerie
{
    public static function fermer(CompteTresorerie $compte, User $auteur): CompteTresorerie
    {
        self::exigerDirection($auteur);

        return DB::transaction(function () use ($compte) {
            $compte = CompteTresorerie::query()->lockForUpdate()->findOrFail($compte->id);

            if (! $compte->actif) {
                throw new OperationRefusee("Le compte « {$compte->nom} » est déjà fermé.");
            }
            if ($compte->solde() !== 0) {
                throw new OperationRefusee("Le compte « {$compte->nom} » ne peut être fermé que lorsque son solde est nul.");
            }

            $compte->update(['actif' => false]);

            return $compte->refresh();
        });
    }

    public static function supprimer(CompteTresorerie $compte, User $auteur): void
    {
        self::exigerDirection($auteur);

        DB::transaction(function () use ($compte): void {
            $compte = CompteTresorerie::query()->lockForUpdate()->findOrFail($compte->id);

            if ($compte->solde() !== 0) {
                throw new OperationRefusee("Le compte « {$compte->nom} » ne peut être supprimé que lorsque son solde est nul.");
            }
            if (! self::peutSupprimer($compte)) {
                throw new OperationRefusee("Le compte « {$compte->nom} » a un historique : fermez-le pour le conserver.");
            }

            $compte->delete();
        });
    }

    public static function peutSupprimer(CompteTresorerie $compte): bool
    {
        foreach (['mouvements_tresorerie', 'depenses', 'decaissements', 'achats', 'encaissements', 'declarations_apports'] as $table) {
            if (DB::table($table)->where('compte_id', $compte->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    private static function exigerDirection(User $auteur): void
    {
        if (! $auteur->can('gerer-parametres')) {
            throw new OperationRefusee('Seule la direction de LY AGRICOLE peut fermer ou supprimer un compte de trésorerie.');
        }
    }
}
