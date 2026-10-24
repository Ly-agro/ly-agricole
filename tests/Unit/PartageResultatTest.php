<?php

namespace Tests\Unit;

use App\Exceptions\OperationRefusee;
use App\Services\PartageResultat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contrat de campagne, art. 12 à 14. Les deux premiers tests reprennent MOT POUR MOT les
 * exemples de l'article 14 : collecte 10 000 000 FCFA, apport de LY 2 500 000 FCFA, un
 * investisseur à 2 000 000 FCFA (20 % du collecté).
 */
class PartageResultatTest extends TestCase
{
    /** Investisseur 1 = celui de l'exemple (20 %) ; les autres complètent les 10 M collectés. */
    private const INVESTIS = [1 => 2_000_000, 2 => 3_000_000, 3 => 5_000_000];

    private const APPORT_LY = 2_500_000;

    #[Test]
    public function exemple_1_de_l_article_14_campagne_beneficiaire(): void
    {
        $r = PartageResultat::calculer(2_500_000, self::INVESTIS, self::APPORT_LY);

        $this->assertSame(PartageResultat::BENEFICE, $r['sens']);
        $this->assertSame(10_000_000, $r['collecte']);
        $this->assertSame(12_500_000, $r['fonds']);
        // Enveloppe Investisseurs : 40 % × 2 500 000 = 1 000 000 ; LY : 60 % = 1 500 000.
        $this->assertSame(1_000_000, $r['part_investisseurs']);
        $this->assertSame(1_500_000, $r['part_ly']);
        // Quote-part : 20 % × 1 000 000 = 200 000 ; il perçoit 2 000 000 + 200 000 = 2 200 000.
        $this->assertSame(200_000, $r['lignes'][1]['part']);
        $this->assertSame(2_200_000, $r['lignes'][1]['somme_due']);
        $this->assertSame(0, $r['non_impute']);
    }

    #[Test]
    public function exemple_2_de_l_article_14_campagne_deficitaire(): void
    {
        $r = PartageResultat::calculer(-1_000_000, self::INVESTIS, self::APPORT_LY);

        $this->assertSame(PartageResultat::PERTE, $r['sens']);
        // Part des investisseurs : 1 000 000 × (10 000 000 ÷ 12 500 000) = 800 000 ; LY : 200 000.
        $this->assertSame(800_000, $r['part_investisseurs']);
        $this->assertSame(200_000, $r['part_ly']);
        // Part de l'investisseur : 20 % × 800 000 = 160 000 ; il perçoit 2 000 000 − 160 000 = 1 840 000.
        $this->assertSame(160_000, $r['lignes'][1]['part']);
        $this->assertSame(1_840_000, $r['lignes'][1]['somme_due']);
        $this->assertSame(0, $r['non_impute']);
    }

    #[Test]
    public function un_resultat_nul_rend_a_chacun_son_capital(): void
    {
        $r = PartageResultat::calculer(0, self::INVESTIS, self::APPORT_LY);

        $this->assertSame(PartageResultat::NUL, $r['sens']);
        foreach (self::INVESTIS as $id => $investi) {
            $this->assertSame($investi, $r['lignes'][$id]['somme_due']);
            $this->assertSame(0, $r['lignes'][$id]['part']);
        }
    }

    #[Test]
    public function une_perte_par_faute_de_gestion_est_supportee_par_ly_seule(): void
    {
        $r = PartageResultat::calculer(-1_000_000, self::INVESTIS, self::APPORT_LY, fauteLy: true);

        // Art. 13.4 : les investisseurs reprennent tout leur capital, LY supporte les 1 000 000.
        $this->assertTrue($r['faute_ly']);
        $this->assertSame(0, $r['part_investisseurs']);
        $this->assertSame(1_000_000, $r['part_ly']);
        foreach (self::INVESTIS as $id => $investi) {
            $this->assertSame($investi, $r['lignes'][$id]['somme_due']);
        }
    }

    #[Test]
    public function la_faute_de_gestion_ne_change_rien_a_un_benefice(): void
    {
        $sans = PartageResultat::calculer(2_500_000, self::INVESTIS, self::APPORT_LY);
        $avec = PartageResultat::calculer(2_500_000, self::INVESTIS, self::APPORT_LY, fauteLy: true);

        $this->assertSame($sans['lignes'], $avec['lignes']);
        $this->assertFalse($avec['faute_ly']);
    }

    #[Test]
    public function l_apport_de_ly_ne_change_pas_la_repartition_du_benefice(): void
    {
        // Art. 7.3 : l'apport propre « ne modifie pas la répartition du bénéfice ».
        $avec = PartageResultat::calculer(2_500_000, self::INVESTIS, self::APPORT_LY);
        $sans = PartageResultat::calculer(2_500_000, self::INVESTIS, 0);

        $this->assertSame($sans['lignes'], $avec['lignes']);
        $this->assertSame($sans['part_ly'], $avec['part_ly']);
    }

    #[Test]
    public function sans_apport_de_ly_les_investisseurs_portent_toute_la_perte(): void
    {
        // Art. 13.2 avec un apport nul : collecté ÷ fonds = 1.
        $r = PartageResultat::calculer(-1_000_000, self::INVESTIS, 0);

        $this->assertSame(1_000_000, $r['part_investisseurs']);
        $this->assertSame(0, $r['part_ly']);
        $this->assertSame(200_000, $r['lignes'][1]['part']);
    }

    #[Test]
    public function une_perte_plus_grande_que_les_fonds_ne_fait_pas_devoir_d_argent_aux_investisseurs(): void
    {
        // Perte de 20 M sur 12,5 M de fonds : la part théorique des investisseurs (16 M) dépasse leurs 10 M.
        $r = PartageResultat::calculer(-20_000_000, self::INVESTIS, self::APPORT_LY);

        foreach (self::INVESTIS as $id => $investi) {
            $this->assertSame(0, $r['lignes'][$id]['somme_due']);
            $this->assertSame($investi, $r['lignes'][$id]['part']);
        }
        $this->assertSame(10_000_000, $r['part_investisseurs']);
        // 16 M attribués aux investisseurs − 10 M investis = 6 M que le contrat ne leur fait pas supporter.
        $this->assertSame(6_000_000, $r['non_impute']);
        $this->assertSame(20_000_000, $r['part_investisseurs'] + $r['part_ly'] + $r['non_impute']);
    }

    /** @return array<string, array{int, array<int, int>, int}> */
    public static function cas(): array
    {
        return [
            'bénéfice qui ne se divise pas' => [1_000_001, [1 => 700_000, 2 => 900_000, 3 => 1_100_003], 250_000],
            'perte qui ne se divise pas' => [-999_999, [1 => 500_000, 2 => 500_000, 3 => 500_000], 123_457],
            'trois parts égales, reste de 1 franc' => [100_000, [1 => 500_000, 2 => 500_000, 3 => 500_000], 0],
            'un seul investisseur' => [777_777, [9 => 500_000], 2_500_000],
            'dix investisseurs' => [4_321_987, array_combine(range(1, 10), [500_000, 600_000, 700_000, 800_000, 900_000, 1_000_000, 1_100_000, 1_200_000, 1_300_000, 1_900_000]), 2_500_000],
            'petit résultat' => [3, [1 => 500_000, 2 => 500_000], 0],
            'petite perte' => [-3, [1 => 500_000, 2 => 1_500_000], 2_500_000],
        ];
    }

    /**
     * @param  array<int, int>  $investis
     */
    #[Test]
    #[DataProvider('cas')]
    public function aucun_franc_n_est_cree_ni_perdu(int $resultat, array $investis, int $apportLy): void
    {
        $r = PartageResultat::calculer($resultat, $investis, $apportLy);

        $this->assertSame(abs($resultat), $r['part_investisseurs'] + $r['part_ly'] + $r['non_impute']);
        // La somme des parts individuelles est exactement la part globale des investisseurs.
        $this->assertSame($r['part_investisseurs'], array_sum(array_column($r['lignes'], 'part')));
        foreach ($r['lignes'] as $id => $ligne) {
            $this->assertGreaterThanOrEqual(0, $ligne['part']);
            $this->assertSame($resultat >= 0 ? $investis[$id] + $ligne['part'] : $investis[$id] - $ligne['part'], $ligne['somme_due']);
        }
    }

    #[Test]
    public function le_reste_va_aux_plus_forts_restes_puis_au_plus_petit_identifiant(): void
    {
        // 100 000 × 40 % = 40 000 entre trois parts égales : 13 333 chacun, 1 franc de reste → investisseur 1.
        $r = PartageResultat::calculer(100_000, [3 => 500_000, 1 => 500_000, 2 => 500_000], 0);

        $this->assertSame([1 => 13_334, 2 => 13_333, 3 => 13_333], array_map(fn (array $l) => $l['part'], $r['lignes']));
    }

    #[Test]
    public function l_ordre_dans_lequel_on_donne_les_investisseurs_ne_change_rien(): void
    {
        $a = PartageResultat::calculer(1_000_001, [1 => 700_000, 2 => 900_000, 3 => 1_100_003], 0);
        $b = PartageResultat::calculer(1_000_001, [3 => 1_100_003, 1 => 700_000, 2 => 900_000], 0);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function la_part_globale_est_arrondie_au_plus_proche_moitie_vers_le_haut(): void
    {
        // 40 % de 12 = 4,8 → 5 ; 40 % de 1 = 0,4 → 0 ; 40 % de 2 = 0,8 → 1 ; 40 % de 5 = 2.
        $this->assertSame(5, PartageResultat::calculer(12, [1 => 500_000], 0)['part_investisseurs']);
        $this->assertSame(0, PartageResultat::calculer(1, [1 => 500_000], 0)['part_investisseurs']);
        $this->assertSame(1, PartageResultat::calculer(2, [1 => 500_000], 0)['part_investisseurs']);
        $this->assertSame(2, PartageResultat::calculer(5, [1 => 500_000], 0)['part_investisseurs']);
    }

    /** @return array<string, array{int, array<int, int>, int, string}> */
    public static function refus(): array
    {
        return [
            'aucun investisseur' => [1_000, [], 0, 'Aucun apport'],
            'montant investi nul' => [1_000, [1 => 0], 0, 'supérieur à zéro'],
            'montant investi négatif' => [1_000, [1 => -5, 2 => 500_000], 0, 'supérieur à zéro'],
            'apport de LY négatif' => [1_000, [1 => 500_000], -1, 'négatif'],
            'montants trop grands' => [PHP_INT_MAX, [1 => 500_000], 0, 'trop grands'],
        ];
    }

    /**
     * @param  array<int, int>  $investis
     */
    #[Test]
    #[DataProvider('refus')]
    public function les_entrees_invalides_sont_refusees_avec_un_motif(int $resultat, array $investis, int $apportLy, string $extrait): void
    {
        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($extrait, '/').'/u');

        PartageResultat::calculer($resultat, $investis, $apportLy);
    }

    #[Test]
    public function des_montants_de_plusieurs_milliards_restent_exacts(): void
    {
        // 3 milliards de FCFA de bénéfice, 2 investisseurs de 1,5 et 4,5 milliards.
        $r = PartageResultat::calculer(3_000_000_000, [1 => 1_500_000_000, 2 => 4_500_000_000], 1_000_000_000);

        $this->assertSame(1_200_000_000, $r['part_investisseurs']);
        $this->assertSame(300_000_000, $r['lignes'][1]['part']);
        $this->assertSame(900_000_000, $r['lignes'][2]['part']);
        $this->assertSame(1_800_000_000, $r['part_ly']);
    }
}
