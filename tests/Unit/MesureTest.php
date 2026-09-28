<?php

namespace Tests\Unit;

use App\Support\Mesure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MesureTest extends TestCase
{
    /** @return array<string, array{string, int, ?int}> */
    public static function saisies(): array
    {
        return [
            'kg entiers' => ['500', 3, 500_000],
            'kg à virgule' => ['500,250', 3, 500_250],
            'kg à point' => ['0.005', 3, 5],
            'espaces' => ['1 234,5', 3, 1_234_500],
            'humidité 8,5 %' => ['8,5', 1, 85],
            'KOR 48,50 lbs' => ['48,50', 2, 4_850],
            'trop de décimales' => ['8,55', 1, null],
            'négatif' => ['-3', 3, null],
            'texte' => ['environ 500', 3, null],
            'vide' => ['', 3, null],
        ];
    }

    #[Test]
    #[DataProvider('saisies')]
    public function une_mesure_devient_un_entier_sans_float(string $saisie, int $decimales, ?int $attendu): void
    {
        $this->assertSame($attendu, Mesure::depuisSaisie($saisie, $decimales));
    }

    #[Test]
    public function un_entier_se_reaffiche_avec_ses_decimales(): void
    {
        $this->assertSame('500,250', Mesure::versSaisie(500_250, 3));
        $this->assertSame('8,5', Mesure::versSaisie(85, 1));
        $this->assertSame('', Mesure::versSaisie(null, 2));
    }
}
