<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\PrixMarche;
use App\Models\Produit;
use App\Models\User;
use App\Services\Publications;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Prix bord-champ des campagnes PASSÉES pour les cultures autres que cacao, café et anacarde (voir
 * HistoriquePrixSeeder), relevés le 30 septembre 2026 (docs/Prixrelever1-3). Un prix par année de
 * campagne (octobre à septembre), avec sa source et son lien ; ce sont des informations publiées,
 * jamais le prix officiel d'une campagne de gestion. Campagnes créées « clôturées ». Relançable.
 *
 * Nature des prix, à lire avant diffusion :
 *  - coton, karité, canne à sucre : prix officiels fixés pour la campagne ;
 *  - hévéa, palmier à huile : moyenne des prix officiels mensuels ou par période sur la campagne ;
 *  - riz, maïs et cultures vivrières/maraîchères : prix bord-champ pratiqués de l'ANADER, moyenne
 *    d'une ANNÉE CIVILE Y rangée dans la campagne (Y-1)-Y (9 des 12 mois en commun) ;
 *  - date d'effet : début de l'année de campagne (1er octobre), sauf date d'annonce connue.
 *
 * Écartés volontairement : les prix de marché 2025-2026 sans lien (N'kalo, Fratmat, EspaceAgro…,
 * dont des prix de détail à Abidjan) et les « proxies » ANADER de Prixrelever2 pour hévéa, palmier et
 * riz, contredits par les séries officielles APROMAC, CHPHC et ADERIZ. Sans source : sésame, piment,
 * hibiscus, poivre ; années manquantes laissées vides, jamais estimées.
 */
class HistoriquePrixCulturesSeeder extends Seeder
{
    private const ANNUAIRE = 'https://web.agriculture.gouv.ci/uploads/publications/177557071266.pdf';

    private const APROMAC = 'https://apromac.ci/les-prix-apromac/';

    private const CHPHC = 'https://conseilheveapalmier.ci/';

    /**
     * Prix bord-champ pratiqués (ANADER, Annuaire des statistiques agricoles 2022-2023, tab. 34),
     * années civiles 2021, 2022, 2023 ; null = non publié.
     *
     * @var array<string, array{0: int|null, 1: int|null, 2: int|null}>
     */
    private const ANADER = [
        'mais' => [283, 246, 282], 'tomate' => [395, 476, 473], 'manioc' => [137, 153, 177],
        'igname' => [332, 379, 252], 'banane-plantain' => [156, 207, 338], 'banane-dessert' => [160, 188, 264],
        'ananas' => [456, 375, 259], 'mangue' => [272, 201, 229], 'arachide' => [325, 239, 289],
        'soja' => [340, 433, null], 'gombo' => [348, 442, 521], 'aubergine' => [249, 364, 383],
        'oignon' => [429, 533, 536], 'cola' => [783, 240, null], 'gingembre' => [163, 168, 281],
        'patate-douce' => [143, 299, 244], 'taro' => [180, 299, 270], 'papaye' => [312, 173, 167],
        'niebe' => [443, 433, 484], 'fonio' => [262, 238, 237], 'sorgho' => [589, 343, null],
        'mil' => [401, 486, 419], 'concombre' => [292, 235, 248], 'chou' => [308, 389, 382],
    ];

    /**
     * Par culture, par année de campagne : [FCFA/kg, source, lien, note, date d'effet si connue].
     *
     * @return array<string, array<string, array{0: int, 1: string, 2: string, 3?: string|null, 4?: string}>>
     */
    public static function donnees(): array
    {
        $coton = 'Prix officiel coton graine 1er choix';
        $d = [
            'coton' => [
                '2019-2020' => [300, $coton.' — CGECI', 'https://cgeci.com/cote-divoire-le-prix-du-coton-graine-fixe-a-300-fcfakg-pour-la-campagne-2019-2020-17/'],
                '2020-2021' => [300, $coton.' — Fraternité Matin', 'https://www.fratmat.info/article/207423/', 'KOACI annonce 270 ; 300 retenu (prix « maintenu » en 2021-2022).'],
                '2021-2022' => [300, $coton.' — Financial Afrik', 'https://www.financialafrik.com/2021/10/08/cote-divoire-le-prix-du-coton-graine-fixe-a-300-fcfa-kg-pour-la-campagne-2021-2022/'],
                '2022-2023' => [310, $coton.' — Abidjan.net', 'https://news.abidjan.net/articles/710150'],
                '2023-2024' => [310, $coton.' — Educarriere', 'https://news.educarriere.ci/news-37435'],
                '2024-2025' => [310, $coton.' — AIP', 'https://www.aip.ci/88464/'],
                '2025-2026' => [310, $coton.' — AIP', 'https://www.aip.ci/228182/'],
            ],
            'karite' => [
                '2024-2025' => [250, 'Prix minimum bord-champ des amandes (CCAK) — Financial Afrik', 'https://www.financialafrik.com/2025/08/13/cote-divoire-le-prix-des-amandes-de-karite-bien-sechees-et-triees-fixe-a-250-f-cfa-kg/', 'Pas de prix officiel avant 2025.', '2025-08-13'],
                '2025-2026' => [250, 'Prix minimum bord-champ des amandes (CCAK) — Financial Afrik', 'https://www.financialafrik.com/2026/08/14/karite-la-cote-divoire-fixe-a-250-fcfa-le-prix-bord-champ-pour-la-campagne-2026-2027/', 'Campagne dite « 2026-2027 », ouverte le 14 août 2026.', '2026-08-14'],
            ],
            'sucre' => [
                '2023-2024' => [18, 'Prix de la tonne de canne aux planteurs villageois — Afrik Soir', 'https://afriksoir.net/cote-divoire-tonne-canne-sucre-a-20-250-fcfa-distillerie-sucaf-ci-ferke/', '18 000 FCFA la tonne.'],
                '2024-2025' => [20, 'Prix de la tonne de canne aux planteurs villageois — Afrik Soir', 'https://afriksoir.net/cote-divoire-tonne-canne-sucre-a-20-250-fcfa-distillerie-sucaf-ci-ferke/', '20 250 FCFA la tonne, arrondi à 20 FCFA/kg. Prix 2025-2026 jamais publié.', '2024-10-31'],
            ],
            'hevea' => [
                '2019-2020' => [229, 'Prix moyen APROMAC, année civile 2020 — Eagrici', 'https://www.eagrici.com', 'Moyenne de janvier à décembre 2020 (9 des 12 mois de la campagne).'],
                '2020-2021' => [321, 'Prix moyen APROMAC, année civile 2021 — APROMAC', self::APROMAC, 'Moyenne de janvier à décembre 2021 (9 des 12 mois de la campagne).'],
                '2021-2022' => [355, 'Moyenne des prix mensuels APROMAC — APROMAC', self::APROMAC, 'Oct. 2021 à sept. 2022 (oct. 317, nov. 343, déc. 351, puis 344 à 351).'],
                '2022-2023' => [295, 'Moyenne des prix mensuels APROMAC — APROMAC', self::APROMAC, 'Oct. 2022 à sept. 2023 (oct. 316, nov. 318, déc. 290, puis 298 à 276).'],
                '2023-2024' => [342, 'Moyenne des prix mensuels APROMAC — Conseil Hévéa-Palmier à Huile-Coco', self::CHPHC, 'Oct. 2023 à sept. 2024 (oct. 307, nov. 317, déc. 321, puis 311 à 366).'],
                '2024-2025' => [398, 'Moyenne des prix mensuels APROMAC — Conseil Hévéa-Palmier à Huile-Coco', self::CHPHC, 'Oct. 2024 à sept. 2025 (oct. 395, nov. 434, déc. 425, puis 442 à 343).'],
                '2025-2026' => [409, 'Moyenne des prix mensuels APROMAC — Conseil Hévéa-Palmier à Huile-Coco', self::CHPHC, 'Oct. 2025 à sept. 2026 : 349, 347, 350, 352, 368, 381, 401, 439, 474, 493, 474, 484 (septembre).'],
            ],
            'palmier-huile' => [
                '2021-2022' => [80, 'Prix du régime aux producteurs — Annuaire des statistiques agricoles 2022-2023 (CHPH)', self::ANNUAIRE, '80 FCFA/kg toute l\'année civile 2022.'],
                '2022-2023' => [77, 'Moyenne de campagne du régime — Annuaire des statistiques agricoles 2022-2023 (CHPH)', self::ANNUAIRE, 'Oct. 2022 à mai 2023 à 80, juin à sept. 2023 environ 71 (déduit de la moyenne officielle 2023 de 73,81) : estimation.'],
                '2023-2024' => [66, 'Moyenne de campagne du régime — CHPHC, avis du 10/05/2024', 'https://conseilheveapalmier.ci/2024/05/10/fixation-des-prix-dachat-des-regimes-de-palme-et-de-lhuile-de-palme-brute/', 'Oct. 2023 environ 71 (mécanisme de prix), puis 65 de nov. 2023 à sept. 2024.'],
                '2024-2025' => [76, 'Moyenne de campagne du régime — CHPHC', self::CHPHC, 'Oct. à déc. 2024 à 65, janv. 2025 à 75, fév. à sept. 2025 à 80.'],
                '2025-2026' => [80, 'Prix du régime, 80 000 FCFA/t — CHPHC', self::CHPHC, 'Maintenu d\'octobre 2025 à septembre 2026 (décision du 31/12/2025).'],
            ],
            'riz' => [
                '2020-2021' => [167, 'Prix de vente du paddy (ADERIZ) — Analyse de la compétitivité des filières, 2021', 'https://fr.scribd.com/document/1010260742/'],
                '2021-2022' => [193, 'Paddy bord-champ, moyenne 2022 (ADERIZ) — Annuaire des statistiques agricoles 2022-2023, tab. 27', self::ANNUAIRE, 'Année civile 2022 (9 des 12 mois de la campagne).'],
                '2022-2023' => [210, 'Paddy bord-champ, moyenne 2023 (ADERIZ) — Annuaire des statistiques agricoles 2022-2023, tab. 27', self::ANNUAIRE, 'Année civile 2023 (9 des 12 mois de la campagne).'],
            ],
        ];

        $campagnes = ['2020-2021', '2021-2022', '2022-2023'];
        foreach (self::ANADER as $code => $prix) {
            foreach ($prix as $i => $p) {
                if ($p === null) {
                    continue;
                }
                $an = 2021 + $i;
                $d[$code][$campagnes[$i]] = [$p, "Prix bord-champ pratiqué {$an} (ANADER) — Annuaire des statistiques agricoles 2022-2023, tab. 34", self::ANNUAIRE, "Moyenne de l'année civile {$an} (9 des 12 mois de la campagne)."];
            }
        }

        return $d;
    }

    /** @return array{campagnes: int, prix: int} ce qui a été créé (0 partout au second passage) */
    public function run(): array
    {
        $auteur = User::query()->where('role', Role::Direction)->orderBy('id')->firstOrFail();
        $creees = 0;
        $publies = 0;

        foreach (self::donnees() as $codeProduit => $annees) {
            $produit = Produit::query()->where('code', $codeProduit)->first();
            if ($produit === null) {
                continue;
            }
            foreach ($annees as $annee => $p) {
                $debut = (int) substr($annee, 0, 4);
                $campagne = Campagne::query()->firstOrCreate(
                    ['produit_id' => $produit->id, 'code' => $annee],
                    ['debut' => "{$debut}-10-01", 'fin' => ($debut + 1).'-09-30', 'statut' => StatutCampagne::Cloturee],
                );
                $creees += $campagne->wasRecentlyCreated ? 1 : 0;

                $date = $p[4] ?? "{$debut}-10-01";
                $existe = PrixMarche::query()->where('produit_id', $produit->id)->whereDate('date_effet', $date)->where('prix_kg_fcfa', $p[0])->exists();
                if ($existe) {
                    continue;
                }
                Publications::publierPrix($produit, $p[0], Carbon::parse($date), $p[1], $p[2], $p[3] ?? null, $auteur);
                $publies++;
            }
        }

        return ['campagnes' => $creees, 'prix' => $publies];
    }
}
