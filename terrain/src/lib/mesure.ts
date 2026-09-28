/**
 * Saisies et calculs en ENTIERS (D4, skill argent-et-kilos) — miroir de
 * App\Support\Mesure, Montant et Format côté serveur. Jamais de nombre à virgule :
 * « 500,250 » kg devient 500 250 g par lecture du texte, pas par multiplication.
 * Le serveur reste juge : ces calculs ne servent qu'à l'aperçu sur le téléphone.
 */

const ESPACES = /[\s  ]/g;

/**
 * « 500,250 » avec 3 décimales → 500250 ; « 8,5 » avec 1 → 85. Virgule ou point ;
 * espaces ignorés ; plus de décimales que prévu ⇒ null (on ne devine pas d'arrondi).
 */
export function depuisSaisie(saisie: string | null | undefined, decimales: number): number | null {
    const propre = (saisie ?? '').replace(ESPACES, '').replace(',', '.');
    const m = /^(\d{1,12})(?:\.(\d+))?$/.exec(propre);
    if (m === null) {
        return null;
    }
    const fraction = m[2] ?? '';
    if (fraction.length > decimales) {
        return null;
    }

    return Number.parseInt(m[1] + fraction.padEnd(decimales, '0'), 10);
}

/** Montant ou prix en FCFA entiers : « 1 500 » → 1500 ; « 1500,5 » ou « 1.500 » ⇒ null. */
export function fcfaDepuisSaisie(saisie: string | null | undefined): number | null {
    const propre = (saisie ?? '').replace(ESPACES, '');

    return /^\d{1,12}$/.test(propre) ? Number.parseInt(propre, 10) : null;
}

/**
 * Montant d'un achat = arrondi au franc de net_g × prix / 1000, calculé en BigInt :
 * exact quel que soit le poids.
 */
export function montantAchat(grammes: number, prixKg: number): number {
    return Number((BigInt(grammes) * BigInt(prixKg) + 500n) / 1000n);
}

/** Grammes à retenir pour solder `restantDu` au prix `prixKg` (arrondi au gramme supérieur). */
export function grammesPourSolder(restantDu: number, prixKg: number): number {
    return Number((BigInt(restantDu) * 1000n + BigInt(prixKg) - 1n) / BigInt(prixKg));
}

const MILLIERS = ' ';

function milliers(n: bigint | number): string {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, MILLIERS);
}

export function fcfa(montant: number | null | undefined): string {
    if (montant === null || montant === undefined) {
        return '—';
    }

    return (montant < 0 ? '-' : '') + milliers(Math.abs(montant)) + ' FCFA';
}

export function kg(grammes: number | null | undefined): string {
    if (grammes === null || grammes === undefined) {
        return '—';
    }
    const absolu = Math.abs(grammes);
    const reste = absolu % 1000;

    return (grammes < 0 ? '-' : '') + milliers(Math.trunc(absolu / 1000)) + (reste === 0 ? '' : ',' + String(reste).padStart(3, '0')) + ' kg';
}
