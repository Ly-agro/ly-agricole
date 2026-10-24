<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    private const FINE = "\u{202F}";

    #[Test]
    public function les_montants_s_affichent_en_fcfa_avec_espaces_fines(): void
    {
        $this->assertSame('0 FCFA', Format::fcfa(0));
        $this->assertSame('3'.self::FINE.'000'.self::FINE.'000 FCFA', Format::fcfa(3_000_000));
        $this->assertSame('—', Format::fcfa(null));
    }

    #[Test]
    public function les_grammes_s_affichent_en_kg_sans_arrondi_cache(): void
    {
        $this->assertSame('500 kg', Format::kg(500_000));
        $this->assertSame('500,250 kg', Format::kg(500_250));
        $this->assertSame('0,005 kg', Format::kg(5));
        $this->assertSame('-1,500 kg', Format::kg(-1_500));
        $this->assertSame('—', Format::kg(null));
    }

    #[Test]
    public function une_campagne_entiere_en_grammes_depasse_2_puissance_31_sans_perte(): void
    {
        // 3 000 t = 3 × 10⁹ g > 2³¹ (skill argent-et-kilos, § pièges).
        $this->assertSame('3'.self::FINE.'000'.self::FINE.'000,001 kg', Format::kg(3_000_000_001));
    }
}
