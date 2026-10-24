import { describe, expect, it } from 'vitest';
import { decoderNotification, grammesEnKgTexte, LecteurTrames, lireTrame, mesureStandard, SERVICES_BALANCE, sourcePoids, Stabilisateur, trouverNotification } from './balance';

describe('lireTrame', () => {
    it.each([
        ['ST,GS,+  12.50kg', 12_500, true],
        ['US,GS,+  12.50kg', 12_500, false],
        ['ST,NT,+0500.250 kg', 500_250, true],
        ['  12.50 kg', 12_500, null],
        ['W: 12,5 kg', 12_500, null],
        ['+0012g', 12, null],
        ['1250 g', 1_250, null],
        ['0.005kg', 5, null],
        ['500.250', 500_250, null],
        ['500', 500_000, null],
    ])('« %s » → %i g (stable : %s)', (trame, grammes, stable) => {
        expect(lireTrame(trame)).toEqual({ grammes, stable });
    });

    it('applique l\'unité choisie quand la trame n\'en donne pas', () => {
        expect(lireTrame('1250', 'g')).toEqual({ grammes: 1_250, stable: null });
        expect(lireTrame('1250', 'kg')).toEqual({ grammes: 1_250_000, stable: null });
    });

    it('ne devine pas : trop de décimales, négatif, vide, bruit, aberrant', () => {
        expect(lireTrame('12.5001 kg')).toBeNull(); // plus de 3 décimales en kg
        expect(lireTrame('12.5 g')).toBeNull(); // des fractions de gramme
        expect(lireTrame('-12.50 kg')).toBeNull();
        expect(lireTrame('')).toBeNull();
        expect(lireTrame('   ')).toBeNull();
        expect(lireTrame('Erreur')).toBeNull();
        expect(lireTrame('99999999 kg')).toBeNull(); // plus de 7 chiffres
    });

    it('un poids énorme mais lisible est refusé (trame mal lue)', () => {
        expect(lireTrame('60000 kg')).toBeNull();
        expect(lireTrame('49999 kg')).toEqual({ grammes: 49_999_000, stable: null });
    });

    it('reste exact au-delà de 2^31 grammes (jamais de flottant)', () => {
        // 2^31 g = 2 147 483 kg : au-delà de la limite d'un pesage, on vérifie seulement l'exactitude en dessous.
        expect(lireTrame('49999.999 kg')).toEqual({ grammes: 49_999_999, stable: null });
    });
});

describe('LecteurTrames', () => {
    it('coupe aux fins de ligne et garde le morceau incomplet', () => {
        const l = new LecteurTrames();

        expect(l.pousser('ST,GS,+ 12.5')).toEqual([]);
        expect(l.pousser('0kg\r\nUS,GS,+ 13')).toEqual(['ST,GS,+ 12.50kg']);
        expect(l.pousser('.00kg\r')).toEqual(['US,GS,+ 13.00kg']);
    });

    it('ignore les lignes vides et ne grossit pas sans fin', () => {
        const l = new LecteurTrames();

        expect(l.pousser('\r\n\r\n12.5kg\n')).toEqual(['12.5kg']);
        expect(l.pousser('x'.repeat(300))).toEqual([]);
        expect(l.pousser('\n12kg\n')).toEqual(['12kg']);
    });
});

describe('Stabilisateur', () => {
    it('stable après 5 lectures identiques de suite', () => {
        const s = new Stabilisateur();
        const lecture = { grammes: 12_500, stable: null };

        expect([1, 2, 3, 4].map(() => s.ajouter(lecture).stable)).toEqual([false, false, false, false]);
        expect(s.ajouter(lecture)).toEqual({ grammes: 12_500, stable: true });
    });

    it('une lecture différente repart de zéro', () => {
        const s = new Stabilisateur(3);
        s.ajouter({ grammes: 100, stable: null });
        s.ajouter({ grammes: 100, stable: null });

        expect(s.ajouter({ grammes: 101, stable: null }).stable).toBe(false);
        expect(s.ajouter({ grammes: 101, stable: null }).stable).toBe(false);
        expect(s.ajouter({ grammes: 101, stable: null }).stable).toBe(true);
    });

    it('la balance qui dit ST est crue tout de suite, et US casse la série', () => {
        const s = new Stabilisateur();

        expect(s.ajouter({ grammes: 5_000, stable: true }).stable).toBe(true);
        expect(s.ajouter({ grammes: 5_000, stable: false }).stable).toBe(false);
        expect(s.ajouter({ grammes: 5_000, stable: null }).stable).toBe(false);
    });

    it('une balance vide n\'est jamais « stable »', () => {
        const s = new Stabilisateur(2);

        expect(s.ajouter({ grammes: 0, stable: true }).stable).toBe(false);
        expect(s.ajouter({ grammes: 0, stable: null }).stable).toBe(false);
        expect(s.ajouter({ grammes: 0, stable: null }).stable).toBe(false);
    });

    it('réinitialiser oublie la série', () => {
        const s = new Stabilisateur(2);
        s.ajouter({ grammes: 10, stable: null });
        s.reinitialiser();

        expect(s.ajouter({ grammes: 10, stable: null }).stable).toBe(false);
    });
});

describe('mesureStandard (Weight Scale 0x2A9D)', () => {
    it('SI : 2 octets little-endian × 5 g', () => {
        // 0x1388 = 5000 → 25 000 g.
        expect(mesureStandard(Uint8Array.of(0x00, 0x88, 0x13))).toBe(25_000);
        expect(mesureStandard(Uint8Array.of(0x00, 0x01, 0x00))).toBe(5);
    });

    it('refuse les livres, une mesure non aboutie et une trame trop courte', () => {
        expect(mesureStandard(Uint8Array.of(0x01, 0x88, 0x13))).toBeNull();
        expect(mesureStandard(Uint8Array.of(0x00, 0xff, 0xff))).toBeNull();
        expect(mesureStandard(Uint8Array.of(0x00, 0x88))).toBeNull();
    });
});

describe('grammesEnKgTexte', () => {
    it.each([[500_250, '500,25'], [500_000, '500'], [250, '0,25'], [1, '0,001'], [12_005, '12,005'], [0, '0']])('%i g → « %s »', (g, texte) => {
        expect(grammesEnKgTexte(g)).toBe(texte);
    });
});

describe('sourcePoids', () => {
    it('balance seulement si le champ vaut exactement le poids pris', () => {
        expect(sourcePoids('500,25', 500_250)).toBe('balance');
        expect(sourcePoids('500,250', 500_250)).toBe('balance');
        expect(sourcePoids('500,3', 500_250)).toBe('manuel');
        expect(sourcePoids('500,25', null)).toBe('manuel');
        expect(sourcePoids('', 500_250)).toBe('manuel');
    });
});

describe('services et notifications', () => {
    it('trouve le service connu et exige sa caractéristique', () => {
        const ffe0 = SERVICES_BALANCE[1];

        expect(trouverNotification([{ uuid: ffe0.service.toUpperCase(), notifications: [ffe0.notification] }])).toEqual(ffe0);
        expect(trouverNotification([{ uuid: ffe0.service, notifications: [] }])).toBeNull();
        expect(trouverNotification([{ uuid: 'inconnu', notifications: ['x'] }])).toBeNull();
    });

    it('décode une notification texte en plusieurs lectures et une notification standard en une', () => {
        const lecteur = new LecteurTrames();
        const texte = new TextEncoder().encode('ST,GS,+ 12.50kg\r\nUS,GS,+ 12.60kg\r\n');

        expect(decoderNotification('texte', texte, lecteur, 'kg')).toEqual([
            { grammes: 12_500, stable: true }, { grammes: 12_600, stable: false },
        ]);
        expect(decoderNotification('standard', Uint8Array.of(0x00, 0x88, 0x13), lecteur, 'kg')).toEqual([{ grammes: 25_000, stable: null }]);
        expect(decoderNotification('standard', Uint8Array.of(0x01, 0x00, 0x00), lecteur, 'kg')).toEqual([]);
    });
});
