import { describe, expect, it } from 'vitest';
import { depuisSaisie, fcfa, fcfaDepuisSaisie, grammesPourSolder, kg, montantAchat } from './mesure';

describe('saisies en entiers', () => {
    it('lit des kilos en grammes sans virgule flottante', () => {
        expect(depuisSaisie('500,250', 3)).toBe(500_250);
        expect(depuisSaisie('500.25', 3)).toBe(500_250);
        expect(depuisSaisie(' 1 000 ', 3)).toBe(1_000_000);
        // 0,1 + 0,2 en float ferait 0,30000000000000004 : ici non.
        expect(depuisSaisie('0,3', 3)).toBe(300);
    });

    it('refuse plus de décimales que prévu, les signes et le vide', () => {
        expect(depuisSaisie('500,2501', 3)).toBeNull();
        expect(depuisSaisie('8,55', 1)).toBeNull();
        expect(depuisSaisie('-5', 3)).toBeNull();
        expect(depuisSaisie('', 3)).toBeNull();
        expect(depuisSaisie('1e3', 3)).toBeNull();
    });

    it('lit un prix en FCFA entiers seulement', () => {
        expect(fcfaDepuisSaisie('1 500')).toBe(1500);
        expect(fcfaDepuisSaisie('1500,5')).toBeNull();
        expect(fcfaDepuisSaisie('1.500')).toBeNull();
    });
});

describe('calculs comme le serveur', () => {
    it('montant = intdiv(net × prix + 500, 1000)', () => {
        expect(montantAchat(500_000, 425)).toBe(212_500);
        expect(montantAchat(235_295, 425)).toBe(100_000); // 99 999,375 → 100 000
        expect(montantAchat(1_001, 1)).toBe(1);
        expect(montantAchat(1_500, 1)).toBe(2);
    });

    it('reste exact au-delà de 2^53 en intermédiaire', () => {
        expect(montantAchat(100_000_000, 99_999_999)).toBe(9_999_999_900_000);
    });

    it('grammes pour solder, arrondis au gramme supérieur', () => {
        expect(grammesPourSolder(3_000_000, 425)).toBe(7_058_824);
        expect(grammesPourSolder(85_000, 425)).toBe(200_000);
    });

    it('affiche comme le bureau', () => {
        expect(kg(500_250)).toBe('500,250 kg');
        expect(kg(1_000_000)).toBe('1 000 kg');
        expect(fcfa(212_500)).toBe('212 500 FCFA');
    });
});
