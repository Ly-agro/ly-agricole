<?php

namespace Tests\Unit;

use App\Support\Montant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MontantTest extends TestCase
{
    /** @return array<string, array{string, ?int}> */
    public static function saisies(): array
    {
        return [
            'simple' => ['1500000', 1_500_000],
            'espaces' => ['1 500 000', 1_500_000],
            'espace insécable' => ["1\u{00A0}500\u{202F}000", 1_500_000],
            'espaces autour' => ['  25000 ', 25_000],
            'point ambigu' => ['1.500', null],
            'virgule' => ['1500,5', null],
            'négatif' => ['-500', null],
            'lettres' => ['cinq mille', null],
            'vide' => ['', null],
            'trop long' => ['1234567890123', null],
        ];
    }

    #[Test]
    #[DataProvider('saisies')]
    public function une_saisie_ambigue_n_est_jamais_devinee(string $saisie, ?int $attendu): void
    {
        $this->assertSame($attendu, Montant::depuisSaisie($saisie));
    }
}
