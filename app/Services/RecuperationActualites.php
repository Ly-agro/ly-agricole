<?php

namespace App\Services;

use App\Exceptions\OperationRefusee;
use App\Models\Actualite;
use App\Models\SourceActualites;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * Récupère des actualités sur des flux RSS / Atom choisis par la direction.
 *
 * Règles : (1) tout arrive en BROUILLON, la direction relit et publie — rien de récupéré n'est
 * public de lui-même ; (2) on ne garde qu'un titre, un court extrait en texte simple et le lien vers
 * l'article d'origine, jamais l'article entier ; (3) aucune adresse interne (localhost, réseau privé)
 * n'est appelée ; (4) une même actualité n'est jamais importée deux fois ; (5) les PRIX ne sont pas
 * récupérés ici : un prix se saisit avec sa source et sa date (Publications::publierPrix).
 */
class RecuperationActualites
{
    public const TAILLE_MAX = 2_000_000;

    public const EXTRAIT_MAX = 400;

    public const ELEMENTS_MAX = 30;

    /** Une source est ajoutée par la direction seulement. */
    public static function ajouterSource(string $nom, string $url, User $auteur): SourceActualites
    {
        if (! $auteur->can('gerer-publications')) {
            throw new OperationRefusee('Seule la direction choisit les sources d\'actualités.');
        }
        $nom = trim($nom);
        $url = trim($url);
        if ($nom === '' || mb_strlen($nom) > 150) {
            throw new OperationRefusee('Le nom de la source est obligatoire (150 caractères au plus).');
        }
        self::verifierAdresse($url);

        return SourceActualites::query()->create(['nom' => $nom, 'url' => $url, 'actif' => true, 'cree_par' => $auteur->id]);
    }

    /** @return array{nouveaux: int, ignores: int} */
    public static function recuperer(SourceActualites $source, User $auteur): array
    {
        try {
            $elements = self::lire($source->url);
        } catch (OperationRefusee $e) {
            $source->update(['derniere_recuperation_at' => now(), 'dernier_statut' => 'erreur', 'dernier_message' => mb_substr($e->getMessage(), 0, 300), 'dernier_nb' => 0]);
            throw $e;
        }

        $nouveaux = 0;
        $ignores = 0;
        foreach ($elements as $e) {
            $identifiant = sha1($source->id.'|'.$e['lien']);
            if (Actualite::query()->where('identifiant_externe', $identifiant)->exists()) {
                $ignores++;

                continue;
            }
            Actualite::query()->create([
                'titre' => $e['titre'], 'contenu' => $e['extrait'], 'publie' => false, 'publie_le' => null,
                'origine' => 'externe', 'source_nom' => $source->nom, 'lien_source' => $e['lien'],
                'date_source' => $e['date']?->toDateString(), 'identifiant_externe' => $identifiant, 'cree_par' => $auteur->id,
            ]);
            $nouveaux++;
        }

        $source->update(['derniere_recuperation_at' => now(), 'dernier_statut' => 'ok', 'dernier_message' => null, 'dernier_nb' => $nouveaux]);

        return ['nouveaux' => $nouveaux, 'ignores' => $ignores];
    }

    /**
     * Toutes les sources actives (commande planifiée : l'auteur des brouillons est celui qui a ajouté la source). Une source en panne n'empêche pas les autres.
     *
     * @return array{nouveaux: int, erreurs: int}
     */
    public static function toutesLesSources(?User $auteur = null): array
    {
        $nouveaux = 0;
        $erreurs = 0;
        foreach (SourceActualites::query()->where('actif', true)->orderBy('id')->get() as $source) {
            try {
                $nouveaux += self::recuperer($source, $auteur ?? $source->auteur)['nouveaux'];
            } catch (OperationRefusee) {
                $erreurs++;
            }
        }

        return ['nouveaux' => $nouveaux, 'erreurs' => $erreurs];
    }

    /**
     * @return list<array{titre: string, extrait: string, lien: string, date: ?Carbon}>
     */
    public static function lire(string $url): array
    {
        self::verifierAdresse($url);

        try {
            $reponse = Http::timeout(10)->withOptions(['allow_redirects' => false])
                ->withHeaders(['User-Agent' => 'LY-AGRICOLE-vitrine/1.0', 'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml'])
                ->get($url);
        } catch (Throwable) {
            throw new OperationRefusee('La source ne répond pas.');
        }
        if (! $reponse->successful()) {
            throw new OperationRefusee('La source a refusé la demande (code '.$reponse->status().').');
        }
        $corps = $reponse->body();
        if (strlen($corps) > self::TAILLE_MAX) {
            throw new OperationRefusee('La réponse de la source est trop volumineuse.');
        }

        return self::analyser($corps);
    }

    /**
     * @return list<array{titre: string, extrait: string, lien: string, date: ?Carbon}>
     */
    public static function analyser(string $xml): array
    {
        // Une déclaration de type de document sert aux attaques par entités : on refuse le flux.
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new OperationRefusee('Ce flux n\'est pas au format attendu.');
        }
        $ancien = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($ancien);
        if ($doc === false) {
            throw new OperationRefusee('Ce flux n\'est pas un flux RSS ou Atom lisible.');
        }

        /** @var list<array{string, string, string, string}> $noeuds */
        $noeuds = [];
        foreach ($doc->channel->item ?? [] as $item) {
            $noeuds[] = [(string) $item->title, (string) $item->link, (string) $item->description, (string) $item->pubDate];
        }
        if ($noeuds === []) {
            foreach ($doc->entry ?? [] as $entree) {
                $lien = '';
                foreach ($entree->link as $l) {
                    $rel = (string) $l['rel'];
                    if ($rel === '' || $rel === 'alternate') {
                        $lien = (string) $l['href'];
                        break;
                    }
                }
                $date = (string) $entree->updated !== '' ? (string) $entree->updated : (string) $entree->published;
                $noeuds[] = [(string) $entree->title, $lien, (string) $entree->summary, $date];
            }
        }

        $elements = [];
        foreach ($noeuds as [$titre, $lien, $texte, $date]) {
            $titre = self::simple($titre);
            $lien = trim($lien);
            if ($titre === '' || ! self::lienPublicValide($lien)) {
                continue;
            }
            $extrait = self::simple($texte);
            if (mb_strlen($extrait) > self::EXTRAIT_MAX) {
                $extrait = rtrim(mb_substr($extrait, 0, self::EXTRAIT_MAX - 1)).'…';
            }
            if (mb_strlen($extrait) < 10) {
                $extrait = $titre.' — à lire sur le site de la source.';
            }
            $elements[] = ['titre' => mb_substr($titre, 0, 200), 'extrait' => $extrait, 'lien' => mb_substr($lien, 0, 500), 'date' => self::date($date)];
            if (count($elements) >= self::ELEMENTS_MAX) {
                break;
            }
        }

        return $elements;
    }

    /** Texte simple : sans balise, sans entité, espaces réduits. */
    private static function simple(string $texte): string
    {
        $texte = html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = preg_replace('/[\p{Cc}\s]+/u', ' ', $texte);

        return trim($texte ?? '');
    }

    private static function date(string $texte): ?Carbon
    {
        if (trim($texte) === '') {
            return null;
        }
        try {
            $date = Carbon::parse($texte);
        } catch (Throwable) {
            return null;
        }

        return $date->isFuture() ? null : $date;
    }

    private static function lienPublicValide(string $lien): bool
    {
        return preg_match('#^https?://#i', $lien) === 1 && filter_var($lien, FILTER_VALIDATE_URL) !== false;
    }

    /** http(s) seulement, et jamais vers une machine interne (localhost, réseau privé, adresse réservée). */
    public static function verifierAdresse(string $url): void
    {
        if (mb_strlen($url) > 500 || ! self::lienPublicValide($url)) {
            throw new OperationRefusee('L\'adresse doit commencer par http:// ou https://.');
        }
        $hote = trim(strtolower((string) parse_url($url, PHP_URL_HOST)), '[]');
        if ($hote === '' || $hote === 'localhost' || str_ends_with($hote, '.localhost') || str_ends_with($hote, '.local') || str_ends_with($hote, '.internal')) {
            throw new OperationRefusee('Cette adresse est interne : elle n\'est pas acceptée.');
        }
        $adresses = filter_var($hote, FILTER_VALIDATE_IP) !== false ? [$hote] : (gethostbynamel($hote) ?: []);
        foreach ($adresses as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new OperationRefusee('Cette adresse est interne : elle n\'est pas acceptée.');
            }
        }
    }
}
