<?php

namespace App\Services;

use App\Models\Producteur;

/**
 * Doublons et prête-noms (cahier des charges §10, carte producteur).
 *
 * - Même pièce d'identité : BLOQUANT (une pièce = une personne). La base le garantit
 *   aussi (index unique) ; ici on donne un message lisible.
 * - Même téléphone ou même numéro Mobile Money : ALERTE, à confirmer explicitement.
 *   Une famille partage souvent un téléphone ; mais un même numéro Mobile Money sur
 *   plusieurs fiches est aussi le signe d'un prête-nom.
 */
class DetectionDoublons
{
    /**
     * @param  array{piece_type?: ?string, piece_numero?: ?string, telephone?: ?string, numero_mobile_money?: ?string}  $donnees
     * @return array{bloquants: list<string>, alertes: list<string>}
     */
    public static function verifier(array $donnees, ?string $ignorerId = null): array
    {
        $bloquants = [];
        $alertes = [];

        $requete = fn () => Producteur::query()->when($ignorerId, fn ($q) => $q->whereKeyNot($ignorerId));

        if (filled($donnees['piece_type'] ?? null) && filled($donnees['piece_numero'] ?? null)) {
            foreach ($requete()->where('piece_type', $donnees['piece_type'])->where('piece_numero', $donnees['piece_numero'])->get() as $p) {
                $bloquants[] = "Cette pièce d'identité est déjà celle de {$p->nomComplet()} ({$p->code}).";
            }
        }

        foreach (['telephone' => 'téléphone', 'numero_mobile_money' => 'numéro Mobile Money'] as $champ => $libelle) {
            $numero = $donnees[$champ] ?? null;
            if (blank($numero)) {
                continue;
            }

            $memes = $requete()->where(fn ($q) => $q->where('telephone', $numero)->orWhere('numero_mobile_money', $numero))->get();
            foreach ($memes as $p) {
                $alertes[] = "Le {$libelle} {$numero} figure déjà sur la fiche de {$p->nomComplet()} ({$p->code}).";
            }
        }

        return ['bloquants' => $bloquants, 'alertes' => array_values(array_unique($alertes))];
    }
}
