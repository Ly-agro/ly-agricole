<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Compteurs du serveur pour les numéros lisibles (code de carte producteur…).
 * Verrou de ligne : deux créations simultanées n'obtiennent jamais le même numéro.
 *
 * @property string $nom
 * @property int $valeur
 */
class Compteur extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'nom';

    protected $keyType = 'string';

    protected $fillable = ['nom', 'valeur'];

    public static function suivant(string $nom): int
    {
        return DB::transaction(function () use ($nom) {
            $compteur = static::query()->lockForUpdate()->find($nom)
                ?? static::query()->create(['nom' => $nom, 'valeur' => 0]);

            $compteur->valeur = (int) $compteur->valeur + 1;
            $compteur->save();

            return $compteur->valeur;
        });
    }
}
