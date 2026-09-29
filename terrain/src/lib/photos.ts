import { v7 as uuidv7 } from 'uuid';
import type { BaseTerrain } from './db';

/**
 * Photos du terrain : compressées sur le téléphone AVANT d'être gardées (réseau faible,
 * mémoire du téléphone), avec l'heure et la position de la prise (preuve de pesée).
 */

const COTE_MAX = 1600;
const QUALITE = 0.7;

export async function compresser(fichier: Blob): Promise<Blob> {
    const image = await createImageBitmap(fichier);
    const echelle = Math.min(1, COTE_MAX / Math.max(image.width, image.height));
    const toile = document.createElement('canvas');
    toile.width = Math.round(image.width * echelle);
    toile.height = Math.round(image.height * echelle);
    toile.getContext('2d')!.drawImage(image, 0, 0, toile.width, toile.height);
    image.close();

    return new Promise((ok, echec) => toile.toBlob((b) => (b ? ok(b) : echec(new Error('Compression impossible.'))), 'image/jpeg', QUALITE));
}

export interface PositionGps { lat: number; lng: number; precision: number }

/** Position actuelle, ou null (refus, pas de signal) : une photo sans position reste valable. */
export function positionActuelle(delaiMs = 10_000): Promise<PositionGps | null> {
    return new Promise((ok) => {
        if (!('geolocation' in navigator)) {
            return ok(null);
        }
        navigator.geolocation.getCurrentPosition(
            (p) => ok({ lat: p.coords.latitude, lng: p.coords.longitude, precision: p.coords.accuracy }),
            () => ok(null),
            { enableHighAccuracy: true, timeout: delaiMs, maximumAge: 30_000 },
        );
    });
}

/** Compresse et garde la photo dans la file ; rend son UUID, à mettre dans l'opération. */
export async function garderPhoto(base: BaseTerrain, fichier: Blob): Promise<string> {
    const [blob, position] = await Promise.all([compresser(fichier), positionActuelle()]);
    const uuid = uuidv7();
    await base.photos.add({
        uuid,
        blob,
        prise_at: new Date().toISOString(),
        lat: position?.lat ?? null,
        lng: position?.lng ?? null,
        statut: 'en_attente',
        motif: null,
    });

    return uuid;
}
