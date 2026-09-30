/**
 * Relevé de parcelle en marchant. Les positions sont des flottants (géométrie : la
 * règle « jamais de float » vaut pour l'argent et les poids). La surface affichée ici
 * n'est qu'un aperçu : le bureau la recalcule à partir du contour (App\Services\Geo\Contour).
 */

export type Position = [lng: number, lat: number];

const RAYON_TERRE = 6378137; // WGS84, comme turf.js et le serveur.

const rad = (d: number) => (d * Math.PI) / 180;

/** Distance en mètres entre deux positions (haversine). */
export function distanceM(a: Position, b: Position): number {
    const dLat = rad(b[1] - a[1]);
    const dLng = rad(b[0] - a[0]);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[1])) * Math.cos(rad(b[1])) * Math.sin(dLng / 2) ** 2;

    return 2 * RAYON_TERRE * Math.asin(Math.sqrt(h));
}

/** Surface en m² d'un anneau (fermé ou non), même méthode que le serveur. */
export function surfaceM2(points: Position[]): number {
    const anneau = fermer(points);
    const n = anneau.length;
    if (n < 4) {
        return 0;
    }
    let somme = 0;
    for (let i = 0; i < n; i++) {
        const bas = anneau[i];
        const milieu = anneau[(i + 1) % n];
        const haut = anneau[(i + 2) % n];
        somme += (rad(haut[0]) - rad(bas[0])) * Math.sin(rad(milieu[1]));
    }

    return Math.round(Math.abs((somme * RAYON_TERRE * RAYON_TERRE) / 2));
}

/** Premier point répété à la fin (GeoJSON). */
export function fermer(points: Position[]): Position[] {
    if (points.length === 0) {
        return [];
    }
    const [premier] = points;
    const dernier = points[points.length - 1];

    return premier[0] === dernier[0] && premier[1] === dernier[1] ? points : [...points, premier];
}

export function polygone(points: Position[]): { type: 'Polygon'; coordinates: Position[][] } {
    return { type: 'Polygon', coordinates: [fermer(points).map(([lng, lat]) => [arrondi(lng), arrondi(lat)] as Position)] };
}

/** 7 décimales ≈ 1 cm : au-delà, c'est du bruit de GPS. */
function arrondi(x: number): number {
    return Math.round(x * 1e7) / 1e7;
}

/**
 * Filtre du relevé : un point n'est gardé que s'il est assez précis et assez loin du
 * précédent (sinon, un agent immobile accumule des points qui dansent).
 */
export function garderPoint(points: Position[], nouveau: Position, precisionM: number, precisionMax = 15, ecartMin = 3): boolean {
    if (precisionM > precisionMax) {
        return false;
    }
    const dernier = points[points.length - 1];

    return dernier === undefined || distanceM(dernier, nouveau) >= ecartMin;
}

/** Surface en hectares, 2 décimales, calcul en entiers à partir des m² (comme Format::hectares). */
export function hectares(m2: number): string {
    const centiemes = Math.trunc((m2 + 50) / 100);

    return `${Math.trunc(centiemes / 100)},${String(centiemes % 100).padStart(2, '0')} ha`;
}
