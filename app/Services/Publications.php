<?php

namespace App\Services;

use App\Exceptions\OperationRefusee;
use App\Models\Actualite;
use App\Models\PrixMarche;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce que la vitrine publique affiche : prix bord-champ et actualités, saisis par la direction.
 *
 * Règles :
 *  - un prix est une INFORMATION datée et sourcée (source obligatoire, affichée avec le prix) ; ce
 *    n'est jamais le prix officiel d'une campagne ni un plancher d'achat ;
 *  - un prix ne se corrige pas (registre immuable) : on en publie un nouveau, l'ancien reste dans
 *    l'historique et sert à montrer la variation ;
 *  - une actualité est un texte simple, jamais du HTML ; brouillon tant qu'elle n'est pas publiée ;
 *  - un lien de source doit être une adresse http(s) : jamais `javascript:` ni autre schéma.
 *
 * La récupération AUTOMATIQUE de prix sur un site externe n'est pas branchée : aucune source n'est
 * choisie, les sources officielles ne proposent pas d'interface stable, et un prix faux affiché
 * publiquement engage LY. Voir la question ouverte n° 39.
 */
class Publications
{
    public const PRIX_MAX = 1_000_000;

    public static function publierPrix(Produit $produit, int $prixKg, Carbon $dateEffet, string $source, ?string $url, ?string $note, User $auteur): PrixMarche
    {
        self::verifierDroit($auteur);

        if (! $produit->actif) {
            throw new OperationRefusee("Le produit « {$produit->nom} » est désactivé.");
        }
        if ($prixKg <= 0 || $prixKg > self::PRIX_MAX) {
            throw new OperationRefusee('Le prix au kilo doit être un nombre entier de FCFA, supérieur à zéro.');
        }
        if ($dateEffet->isFuture()) {
            throw new OperationRefusee('La date d\'effet d\'un prix ne peut pas être dans le futur.');
        }
        $source = trim($source);
        if (mb_strlen($source) < 3) {
            throw new OperationRefusee('La source du prix est obligatoire (ex. « Communiqué du 1er octobre »).');
        }
        $url = filled($url) ? trim((string) $url) : null;
        if ($url !== null && ! preg_match('#^https?://[^\s]+$#i', $url)) {
            throw new OperationRefusee('Le lien de la source doit commencer par http:// ou https://.');
        }

        return PrixMarche::query()->create([
            'produit_id' => $produit->id,
            'prix_kg_fcfa' => $prixKg,
            'date_effet' => $dateEffet->toDateString(),
            'source' => mb_substr($source, 0, 255),
            'source_url' => $url === null ? null : mb_substr($url, 0, 500),
            'note' => filled($note) ? mb_substr(trim((string) $note), 0, 500) : null,
            'cree_par' => $auteur->id,
        ]);
    }

    /**
     * Le prix en vigueur de chaque produit (le plus récent), avec l'écart au précédent.
     *
     * @return Collection<int, array{produit: Produit, prix: PrixMarche, precedent: PrixMarche|null, ecart: int|null}>
     */
    public static function prixCourants(): Collection
    {
        $tous = PrixMarche::query()->with('produit')->orderByDesc('date_effet')->orderByDesc('id')->get()->groupBy('produit_id');

        return $tous->map(function (Collection $lignes) {
            /** @var PrixMarche $prix */
            $prix = $lignes->first();
            /** @var PrixMarche|null $precedent */
            $precedent = $lignes->get(1);

            return [
                'produit' => $prix->produit,
                'prix' => $prix,
                'precedent' => $precedent,
                'ecart' => $precedent === null ? null : $prix->prix_kg_fcfa - $precedent->prix_kg_fcfa,
            ];
        })->sortBy(fn (array $l) => mb_strtolower($l['produit']->nom))->values();
    }

    /** @return Collection<int, PrixMarche> Du plus récent au plus ancien. */
    public static function historiquePrix(Produit $produit): Collection
    {
        return PrixMarche::query()->with('auteur')->where('produit_id', $produit->id)->orderByDesc('date_effet')->orderByDesc('id')->get();
    }

    /**
     * Crée ou modifie une actualité. `publie` faux = brouillon.
     *
     * @param  array{titre: string, contenu: string, publie: bool, publie_le?: ?Carbon}  $d
     */
    public static function enregistrerActualite(array $d, User $auteur, ?Actualite $existante = null): Actualite
    {
        self::verifierDroit($auteur);

        $titre = trim($d['titre']);
        $contenu = trim($d['contenu']);
        if ($titre === '' || mb_strlen($titre) > 200) {
            throw new OperationRefusee('Le titre est obligatoire (200 caractères au plus).');
        }
        if (mb_strlen($contenu) < 10 || mb_strlen($contenu) > 10_000) {
            throw new OperationRefusee('Le texte doit faire entre 10 et 10 000 caractères.');
        }

        $publie = (bool) $d['publie'];
        // Publiée sans date : la date du jour ; une actualité déjà publiée garde sa date.
        $date = $publie ? ($d['publie_le'] ?? $existante->publie_le ?? Carbon::today()) : null;

        $attributs = ['titre' => $titre, 'contenu' => $contenu, 'publie' => $publie, 'publie_le' => $date?->toDateString()];

        if ($existante !== null) {
            $existante->update($attributs);

            return $existante->refresh();
        }

        return Actualite::query()->create($attributs + ['cree_par' => $auteur->id]);
    }

    /** @return Collection<int, Actualite> Les plus récentes d'abord, seulement ce que le public peut voir. */
    public static function actualitesVisibles(?int $limite = null): Collection
    {
        $requete = Actualite::query()->visibles()->orderByDesc('publie_le')->orderByDesc('id');

        return ($limite === null ? $requete : $requete->limit($limite))->get();
    }

    private static function verifierDroit(User $auteur): void
    {
        if (! $auteur->can('gerer-publications')) {
            throw new OperationRefusee('Seule la direction publie sur la vitrine.');
        }
    }
}
