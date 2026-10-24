import type { LigneTicket } from './ticket';

/**
 * Encodage ESC/POS (Xprinter, mini POS 58 mm et la plupart des imprimantes thermiques
 * « 58 mm » vendues en Côte d'Ivoire). Table de caractères PC437 (celle par défaut de ces
 * imprimantes) : elle a les minuscules accentuées du français ; le reste est simplifié.
 *
 * La Phomemo M832 n'est PAS une imprimante ESC/POS : A4, protocole à elle. Elle imprime
 * les PDF du bureau avec son pilote.
 */

const ESC = 0x1b;
const GS = 0x1d;
const LF = 0x0a;

/** Caractères présents dans PC437 (code) ; les autres passent par `simplifier`. */
const PC437: Record<string, number> = {
    'Ç': 0x80, 'ü': 0x81, 'é': 0x82, 'â': 0x83, 'ä': 0x84, 'à': 0x85, 'ç': 0x87, 'ê': 0x88, 'ë': 0x89,
    'è': 0x8a, 'ï': 0x8b, 'î': 0x8c, 'Ä': 0x8e, 'É': 0x90, 'ô': 0x93, 'ö': 0x94, 'û': 0x96, 'ù': 0x97,
    'Ö': 0x99, 'Ü': 0x9a, '°': 0xf8,
};

const SIMPLIFIER: Record<string, string> = {
    'À': 'A', 'Â': 'A', 'È': 'E', 'Ê': 'E', 'Ë': 'E', 'Î': 'I', 'Ï': 'I', 'Ô': 'O', 'Ù': 'U', 'Û': 'U',
    'œ': 'oe', 'Œ': 'OE', 'æ': 'ae', '–': '-', '—': '-', '×': 'x', '’': "'", '‘': "'", '“': '"', '”': '"',
    '«': '"', '»': '"', '…': '...', ' ': ' ', ' ': ' ',
};

/** Octets PC437 d'un texte ; tout caractère inconnu devient « ? » (jamais d'octet invalide). */
export function versPc437(texte: string): number[] {
    const octets: number[] = [];
    for (const car of texte) {
        const simple = SIMPLIFIER[car];
        if (simple !== undefined) {
            octets.push(...versPc437(simple));
        } else if (PC437[car] !== undefined) {
            octets.push(PC437[car]);
        } else {
            const code = car.codePointAt(0) ?? 0x3f;
            octets.push(code >= 0x20 && code < 0x7f ? code : 0x3f);
        }
    }

    return octets;
}

/** Suite de commandes ESC/POS pour un ticket, prête à envoyer à l'imprimante. */
export function encoderTicket(lignes: LigneTicket[], lignesAvance = 4): Uint8Array<ArrayBuffer> {
    const o: number[] = [
        ESC, 0x40, // initialiser
        ESC, 0x74, 0x00, // table de caractères PC437
    ];
    for (const l of lignes) {
        o.push(ESC, 0x61, l.centre ? 1 : 0); // alignement
        o.push(ESC, 0x45, l.gras ? 1 : 0); // gras
        o.push(GS, 0x21, l.grand ? 0x11 : 0x00); // double largeur et hauteur
        o.push(...versPc437(l.texte), LF);
    }
    o.push(ESC, 0x45, 0, GS, 0x21, 0, ESC, 0x61, 0); // remettre à zéro
    o.push(ESC, 0x64, lignesAvance); // avancer le papier pour le déchirer

    return Uint8Array.from(o);
}

/** Découpe en paquets (Bluetooth basse énergie : ~20 à 180 octets par écriture). */
export function paquets(donnees: Uint8Array<ArrayBuffer>, taille: number): Uint8Array<ArrayBuffer>[] {
    const r: Uint8Array<ArrayBuffer>[] = [];
    for (let i = 0; i < donnees.length; i += taille) {
        r.push(donnees.slice(i, i + taille));
    }

    return r;
}
