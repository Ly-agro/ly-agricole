import { describe, expect, it, vi } from 'vitest';

vi.mock('@capacitor-community/bluetooth-le', () => ({ BleClient: {} }));

import { encoderTicket, paquets, versPc437 } from './escpos';
import { trouverEcriture } from './imprimante';
import { bonDePesee, LARGEUR, Ticket } from './ticket';

const bon = (surcharge: Partial<Parameters<typeof bonDePesee>[0]> = {}) => bonDePesee({
    uuid: '01a0ed1c-b741-7149-9cb3-2d55bc455288',
    date: new Date(2026, 8, 29, 10, 5),
    fournisseur: 'Coulibaly Awa',
    carte: 'LYP-000001',
    agent: 'Koné Ibrahim',
    produit: 'Anacarde',
    campagne: '2026-2027',
    lot: 'LOT-00001',
    poids_brut_g: 505_000,
    tare_g: 5_000,
    humidite_pour_mille: 85,
    kor_centieme_lbs: null,
    grainage_noix_kg: null,
    prix_kg_fcfa: 425,
    pret: null,
    ...surcharge,
});

function largeurRespectee(t: Ticket) {
    for (const l of t.lignes) {
        expect(l.texte.length, l.texte).toBeLessThanOrEqual(l.grand ? LARGEUR / 2 : LARGEUR);
    }
}

describe('bon de pesée 58 mm', () => {
    it('tient en 32 colonnes et reprend la pesée, le prix et le montant', () => {
        const t = bon();
        largeurRespectee(t);
        const texte = t.enTexte();
        expect(texte).toContain('BON DE PESÉE');
        expect(texte).toContain('Réf. provisoire BC455288');
        expect(texte).toMatch(/^Poids net +500 kg$/m);
        expect(texte).toMatch(/^Humidité +8,5 %$/m);
        // Milliers séparés par une espace fine insécable (comme au bureau) : \s la couvre.
        expect(texte).toMatch(/^Montant +212\s500 FCFA$/m);
        expect(texte).toMatch(/^Payé en espèces +212\s500 FCFA$/m);
        expect(texte).toContain('Bon provisoire');
    });

    it('retenue pour un prêt : même arrondi que la page d\'achat', () => {
        const texte = bon({ pret: { reference: 'LYPR-000001', grammes: 200_000 } }).enTexte();
        // 500 kg × 425 = 212 500 ; 300 kg payés = 127 500 ; retenu = 85 000.
        expect(texte).toMatch(/- 85\s000 FCFA$/m);
        expect(texte).toMatch(/^Payé en espèces +127\s500 FCFA$/m);
    });

    it('un libellé trop long passe à la ligne, la valeur reste à droite', () => {
        const t = new Ticket().paire('Un libellé beaucoup trop long pour une seule ligne', '1 000 FCFA').texte('X'.repeat(70));
        largeurRespectee(t);
        expect(t.enTexte()).toMatch(/ligne +1 000 FCFA$/m);
    });
});

describe('ESC/POS', () => {
    it('garde les accents du français en PC437 et simplifie le reste', () => {
        expect(versPc437('é è à ç')).toEqual([0x82, 0x20, 0x8a, 0x20, 0x85, 0x20, 0x87]);
        expect(versPc437('À – œ')).toEqual([...'A - oe'].map((c) => c.charCodeAt(0)));
        expect(versPc437('1 000')).toEqual([...'1 000'].map((c) => c.charCodeAt(0)));
        expect(versPc437('€')).toEqual([0x3f]);
    });

    it('commence par initialiser l\'imprimante et finit par avancer le papier', () => {
        const octets = encoderTicket(bon().lignes);
        expect(Array.from(octets.slice(0, 5))).toEqual([0x1b, 0x40, 0x1b, 0x74, 0x00]);
        expect(Array.from(octets.slice(-3))).toEqual([0x1b, 0x64, 4]);
        // Le titre est en double taille, centré, en gras.
        const debutTitre = Array.from(octets.slice(5, 14));
        expect(debutTitre).toEqual([0x1b, 0x61, 1, 0x1b, 0x45, 1, 0x1d, 0x21, 0x11]);
        // Aucun octet de contrôle parasite dans le texte : seulement LF comme saut.
        expect(octets.every((o) => o >= 0x20 || [0x0a, 0x1b, 0x1d, 0x00, 0x01, 0x04, 0x11].includes(o))).toBe(true);
    });

    it('découpe en paquets pour le Bluetooth basse énergie', () => {
        const p = paquets(new Uint8Array(250), 100);
        expect(p.map((x) => x.length)).toEqual([100, 100, 50]);
    });

    it('reconnaît le service d\'impression d\'une imprimante 58 mm courante', () => {
        expect(trouverEcriture([
            { uuid: '0000180A-0000-1000-8000-00805F9B34FB', ecritures: [] },
            { uuid: '000018F0-0000-1000-8000-00805F9B34FB', ecritures: ['00002AF1-0000-1000-8000-00805F9B34FB'] },
        ])).toEqual({ service: '000018f0-0000-1000-8000-00805f9b34fb', ecriture: '00002af1-0000-1000-8000-00805f9b34fb' });
        expect(trouverEcriture([{ uuid: '0000180a-0000-1000-8000-00805f9b34fb', ecritures: ['x'] }])).toBeNull();
    });
});
