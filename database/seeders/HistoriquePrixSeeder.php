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
 * Prix bord-champ des campagnes PASSÉES, relevés dans la presse ivoirienne (cacao, café, anacarde),
 * avec leur source. Ce sont des prix publiés à titre d'information, jamais le prix officiel d'une
 * campagne de gestion. Les campagnes créées ici sont « clôturées » : elles n'apparaissent pas dans
 * les listes de travail (prêts, lots) et ne servent qu'à comparer. Relancer ne crée aucun doublon.
 *
 * Réserve : relevé à partir de la presse, pas du site du Conseil du Café-Cacao lui-même ; la date
 * d'effet est celle de l'annonce ou du lancement de la campagne. À faire relire avant diffusion.
 */
class HistoriquePrixSeeder extends Seeder
{
    /**
     * Par produit, par campagne : dates d'ouverture/fermeture, puis les prix (date d'effet, FCFA/kg,
     * source, lien, note). Anacarde : campagne civile (code = année), de février à septembre.
     *
     * @return array<string, list<array{code: string, debut: string, fin: string, prix: list<array{0: string, 1: int, 2: string, 3: string, 4?: string}>}>>
     */
    public static function donnees(): array
    {
        $koaci = 'https://www.koaci.com/article/';
        $abidjan = 'https://news.abidjan.net/articles/';

        return [
            'cacao' => [
                ['code' => '2020-2021', 'debut' => '2020-10-01', 'fin' => '2021-09-30', 'prix' => [
                    ['2020-10-01', 1_000, 'KOACI — campagne 2020-2021', $koaci.'2020/10/01/cote-divoire/economie/cote-divoire-le-prix-du-kilogramme-de-cacao-fixe-a-1000-f-cfa-pour-la-campagne-2020-2021_145480.html'],
                    ['2021-04-01', 750, 'Conseil du Café-Cacao — campagne intermédiaire 2020-2021', 'http://www.conseilcafecacao.ci/index.php?option=com_k2&view=item&id=1073%3Acampagne-intermediaire-du-cacao-2020-2021-le-prix-bord-champ-du-cacao-fixe-a-750-fcfa%2Fkg&Itemid=18', 'Campagne intermédiaire (avril 2021) ; jour exact non vérifié.'],
                ]],
                ['code' => '2021-2022', 'debut' => '2021-10-01', 'fin' => '2022-09-30', 'prix' => [
                    ['2021-10-01', 825, 'Abidjan.net — campagne 2021-2022', $abidjan.'698243/cote-divoire-le-kg-du-cacao-fixe-a-825-fcfa-pour-la-campagne-2021-2022'],
                ]],
                ['code' => '2022-2023', 'debut' => '2022-10-01', 'fin' => '2023-09-30', 'prix' => [
                    ['2022-10-01', 900, 'Abidjan.net — campagne 2022-2023', $abidjan.'712879/campagne-2022-2023-le-prix-bord-champ-du-kilogramme-de-cacao-fixe-a-900-fcfa-et-celui-du-cafe-a-750-fcfa', 'Annoncé le 30 septembre 2022.'],
                ]],
                ['code' => '2023-2024', 'debut' => '2023-10-01', 'fin' => '2024-09-30', 'prix' => [
                    ['2023-10-01', 1_000, 'Abidjan.net — campagne principale 2023-2024', $abidjan.'724364/campagne-principale-de-commercialisation-2023-2024-cafe-cacao-le-prix-du-kg-de-cacao-fixe-a-1000-fcfa-et-celui-du-cafe-a-900-fcfa', 'Annoncé le 30 septembre 2023.'],
                    ['2024-04-02', 1_500, 'AIP — campagne intermédiaire 2023-2024', 'https://www.aip.ci/47454/cote-divoire-aip-le-prix-bord-champ-du-cacao-fixe-a-1-500-fcfa-kg-pour-la-campagne-intermediaire-2023-2024-officiel/', 'Campagne intermédiaire.'],
                ]],
                ['code' => '2024-2025', 'debut' => '2024-10-01', 'fin' => '2025-09-30', 'prix' => [
                    ['2024-10-01', 1_800, 'KOACI — campagne 2024-2025', $koaci.'2024/09/30/cote-divoire/economie/cote-divoire-le-prix-bord-champs-du-cacao-pour-la-campagne-2024-2025-passe-a-1800-fcfa-et-celui-du-cafe-a-1500-fcfa_181279.html', 'Annoncé le 30 septembre 2024.'],
                    ['2025-04-01', 2_200, 'AIP — campagne intermédiaire 2024-2025', 'https://www.aip.ci/174846/cote-divoire-aip-le-prix-dachat-bord-champ-du-cacao-passe-de-1800-a-2200-fcfa-kg-pour-la-campagne-intermediaire-2024-2025/', 'Campagne intermédiaire (à partir d\'avril 2025) ; jour exact non vérifié.'],
                ]],
                ['code' => '2025-2026', 'debut' => '2025-10-01', 'fin' => '2026-09-30', 'prix' => [
                    ['2025-10-01', 2_800, 'Fraternité Matin — campagne 2025-2026', 'https://www.fratmat.info/article/2637240/flash-info/cote-divoire-les-prix-bord-champs-du-cafe-et-du-cacao-respectivement-fixes-a-1700-fcfa-et-2800-fcfa-pour-la-campagne-2025-2026'],
                    ['2026-03-04', 1_200, 'KOACI — campagne intermédiaire 2025-2026', $koaci.'2026/03/04/cote-divoire/societe/cote-divoire-cacao-le-prix-bord-champ-fixe-a-1-200-fcfakg-pour-la-campagne-intermediaire-2025-2026_194830.html', 'Campagne intermédiaire.'],
                ]],
            ],
            'cafe' => [
                ['code' => '2020-2021', 'debut' => '2020-10-01', 'fin' => '2021-09-30', 'prix' => [
                    ['2020-10-01', 550, 'Abidjan.net — cité comme prix précédent de la campagne 2021-2022', $abidjan.'698243/cote-divoire-le-kg-du-cacao-fixe-a-825-fcfa-pour-la-campagne-2021-2022', 'Prix relevé indirectement (« 700 contre 550 ») ; date d\'annonce non vérifiée.'],
                ]],
                ['code' => '2021-2022', 'debut' => '2021-10-01', 'fin' => '2022-09-30', 'prix' => [
                    ['2021-10-01', 700, 'Abidjan.net — campagne 2021-2022', $abidjan.'698243/cote-divoire-le-kg-du-cacao-fixe-a-825-fcfa-pour-la-campagne-2021-2022'],
                ]],
                ['code' => '2022-2023', 'debut' => '2022-10-01', 'fin' => '2023-09-30', 'prix' => [
                    ['2022-10-01', 750, 'Abidjan.net — campagne 2022-2023', $abidjan.'712879/campagne-2022-2023-le-prix-bord-champ-du-kilogramme-de-cacao-fixe-a-900-fcfa-et-celui-du-cafe-a-750-fcfa'],
                ]],
                ['code' => '2023-2024', 'debut' => '2023-10-01', 'fin' => '2024-09-30', 'prix' => [
                    ['2023-10-01', 900, 'Abidjan.net — campagne principale 2023-2024', $abidjan.'724364/campagne-principale-de-commercialisation-2023-2024-cafe-cacao-le-prix-du-kg-de-cacao-fixe-a-1000-fcfa-et-celui-du-cafe-a-900-fcfa'],
                ]],
                ['code' => '2024-2025', 'debut' => '2024-10-01', 'fin' => '2025-09-30', 'prix' => [
                    ['2024-10-01', 1_500, 'KOACI — campagne 2024-2025', $koaci.'2024/09/30/cote-divoire/economie/cote-divoire-le-prix-bord-champs-du-cacao-pour-la-campagne-2024-2025-passe-a-1800-fcfa-et-celui-du-cafe-a-1500-fcfa_181279.html'],
                ]],
                ['code' => '2025-2026', 'debut' => '2025-10-01', 'fin' => '2026-09-30', 'prix' => [
                    ['2025-10-01', 1_700, 'Fraternité Matin — campagne 2025-2026', 'https://www.fratmat.info/article/2637240/flash-info/cote-divoire-les-prix-bord-champs-du-cafe-et-du-cacao-respectivement-fixes-a-1700-fcfa-et-2800-fcfa-pour-la-campagne-2025-2026'],
                ]],
            ],
            'anacarde' => [
                ['code' => '2019', 'debut' => '2019-02-15', 'fin' => '2019-09-30', 'prix' => [
                    ['2019-02-15', 375, 'Journal d\'Abidjan — campagne 2019', 'https://jda.ci/news/economie-agricultureelevage-3342-noix-de-cajou-le-kilogramme-du-prix-bord-champ-fix-375-fcfa-pour-la-campagne-2019', 'Ouverture de la campagne le 15 février 2019.'],
                ]],
                ['code' => '2020', 'debut' => '2020-02-06', 'fin' => '2020-09-30', 'prix' => [
                    ['2020-02-06', 400, 'Fraternité Matin — campagne 2020', 'https://www.fratmat.info/article/201661/regions/campagne-anacarde-2020-le-prix-minimum-bord-champ-de-la-noix-de-cajou-fixe-a-400-fcfa-le-kilogramme'],
                ]],
                ['code' => '2021', 'debut' => '2021-02-04', 'fin' => '2021-09-30', 'prix' => [
                    ['2021-02-04', 305, 'Financial Afrik — campagne 2021', 'https://www.financialafrik.com/2021/02/04/cote-divoire-baisse-du-prix-bord-champ-de-la-noix-de-cajou/', 'Baisse (400 en 2020).'],
                ]],
                ['code' => '2022', 'debut' => '2022-02-04', 'fin' => '2022-09-30', 'prix' => [
                    ['2022-02-04', 305, 'Financial Afrik — campagne 2022', 'https://www.financialafrik.com/2022/01/27/cote-divoire-le-kilogramme-de-la-noix-de-cajou-fixe-a-305-fcfa-pour-la-campagne-2022/', 'Annoncé fin janvier ; ouverture de la campagne le 4 février 2022.'],
                ]],
                ['code' => '2023', 'debut' => '2023-02-03', 'fin' => '2023-09-30', 'prix' => [
                    ['2023-02-03', 315, 'Abidjan.net — campagne 2023', $abidjan.'717538/campagne-2023-de-commercialisation-de-la-noix-de-cajou-le-prix-bord-champ-plancher-obligatoire-du-kg-fixe-a-315-fcfa-contre-305-fcfa-lannee-derniere'],
                ]],
                ['code' => '2024', 'debut' => '2024-02-20', 'fin' => '2024-09-30', 'prix' => [
                    ['2024-02-20', 275, 'Financial Afrik — campagne 2024', 'https://www.financialafrik.com/2024/02/23/cote-divoire-le-prix-bord-champ-de-la-noix-de-cajou-fixe-a-275-fcfa-kg/', 'Annoncé le 20 février 2024.'],
                ]],
                ['code' => '2025', 'debut' => '2025-01-17', 'fin' => '2025-09-30', 'prix' => [
                    ['2025-01-17', 425, 'Abidjan.net — campagne 2025', $abidjan.'738592/cote-divoire-le-prix-bord-champ-de-la-noix-de-cajou-fixe-425-fcfa-kg-pour-la-campagne-2025-officiel'],
                ]],
                ['code' => '2026', 'debut' => '2026-02-06', 'fin' => '2026-09-30', 'prix' => [
                    ['2026-02-06', 400, 'Abidjan.net — campagne 2026', $abidjan.'746557/campagne-2026-de-lanacarde-le-prix-bord-champ-du-kilogramme-de-la-noix-de-cajou-fixe-a-400-fcfa'],
                ]],
            ],
        ];
    }

    /** @return array{campagnes: int, prix: int} ce qui a été créé (0 partout au second passage) */
    public function run(): array
    {
        $auteur = User::query()->where('role', Role::Direction)->orderBy('id')->firstOrFail();
        $creees = 0;
        $publies = 0;

        foreach (self::donnees() as $codeProduit => $campagnes) {
            $produit = Produit::query()->where('code', $codeProduit)->first();
            if ($produit === null) {
                continue;
            }
            foreach ($campagnes as $c) {
                $campagne = Campagne::query()->firstOrCreate(
                    ['produit_id' => $produit->id, 'code' => $c['code']],
                    ['debut' => $c['debut'], 'fin' => $c['fin'], 'statut' => StatutCampagne::Cloturee],
                );
                $creees += $campagne->wasRecentlyCreated ? 1 : 0;

                foreach ($c['prix'] as $p) {
                    $existe = PrixMarche::query()->where('produit_id', $produit->id)->whereDate('date_effet', $p[0])->where('prix_kg_fcfa', $p[1])->exists();
                    if ($existe) {
                        continue;
                    }
                    Publications::publierPrix($produit, $p[1], Carbon::parse($p[0]), $p[2], $p[3], $p[4] ?? null, $auteur);
                    $publies++;
                }
            }
        }

        return ['campagnes' => $creees, 'prix' => $publies];
    }
}
