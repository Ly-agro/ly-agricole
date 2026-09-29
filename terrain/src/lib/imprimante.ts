import { Capacitor } from '@capacitor/core';
import { type BaseTerrain, reglage, regler } from './db';
import { encoderTicket, paquets } from './escpos';
import type { Ticket } from './ticket';

/**
 * Imprimante thermique 58 mm en Bluetooth basse énergie (BLE).
 * - Appli Android : plugin @capacitor-community/bluetooth-le.
 * - Navigateur Chrome (essai depuis un PC) : Web Bluetooth.
 *
 * LIMITE : les imprimantes en Bluetooth « classique » seulement (profil série, SPP) ne
 * sont pas prises en charge ici (question 50). NON VÉRIFIÉ sur une vraie imprimante.
 */

/** Services et caractéristiques d'écriture des imprimantes 58 mm courantes. */
export const SERVICES_CONNUS: { service: string; ecriture: string }[] = [
    { service: '000018f0-0000-1000-8000-00805f9b34fb', ecriture: '00002af1-0000-1000-8000-00805f9b34fb' },
    { service: 'e7810a71-73ae-499d-8c15-faa9aef0c3f2', ecriture: 'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f' },
    { service: '49535343-fe7d-4ae5-8fa9-9fafd205e455', ecriture: '49535343-8841-43f4-a8d4-ecbe34729bb3' },
    { service: '0000ff00-0000-1000-8000-00805f9b34fb', ecriture: '0000ff02-0000-1000-8000-00805f9b34fb' },
];

/** Taille d'une écriture : prudente, beaucoup d'imprimantes refusent au-delà. */
const TAILLE_PAQUET = 100;
const PAUSE_MS = 20;

export interface ImprimanteChoisie { id: string; nom: string }

export class ImpressionImpossible extends Error {}

const pause = (ms: number) => new Promise((r) => setTimeout(r, ms));

export async function imprimanteChoisie(base: BaseTerrain): Promise<ImprimanteChoisie | undefined> {
    return reglage<ImprimanteChoisie>(base, 'imprimante');
}

// --- Web Bluetooth (Chrome) : types minimaux, l'API n'est pas dans lib.dom. ---
interface CaracteristiqueWeb { properties: { write: boolean; writeWithoutResponse: boolean }; writeValueWithoutResponse(d: BufferSource): Promise<void>; writeValue(d: BufferSource): Promise<void> }
interface ServiceWeb { uuid: string; getCharacteristics(): Promise<CaracteristiqueWeb[]>; getCharacteristic(uuid: string): Promise<CaracteristiqueWeb> }
interface AppareilWeb { id: string; name?: string; gatt?: { connect(): Promise<{ getPrimaryServices(): Promise<ServiceWeb[]>; disconnect(): void }> } }
interface BluetoothWeb { requestDevice(o: object): Promise<AppareilWeb> }

let appareilWeb: AppareilWeb | null = null;

function bluetoothWeb(): BluetoothWeb | null {
    return (navigator as unknown as { bluetooth?: BluetoothWeb }).bluetooth ?? null;
}

async function pluginBle() {
    const { BleClient } = await import('@capacitor-community/bluetooth-le');
    // Sans `androidNeverForLocation` : l'appli a déjà la localisation (GPS des parcelles),
    // et l'option exigerait de réécrire le manifeste du plugin.
    await BleClient.initialize();

    return BleClient;
}

/** L'agent choisit l'imprimante une fois ; elle est gardée sur le téléphone. */
export async function choisirImprimante(base: BaseTerrain): Promise<ImprimanteChoisie> {
    const services = SERVICES_CONNUS.map((s) => s.service);
    try {
        if (Capacitor.isNativePlatform()) {
            const ble = await pluginBle();
            const a = await ble.requestDevice({ optionalServices: services });
            const choisie = { id: a.deviceId, nom: a.name ?? 'Imprimante' };
            await regler(base, 'imprimante', choisie);
            return choisie;
        }
        const bt = bluetoothWeb();
        if (!bt) throw new ImpressionImpossible('Ce navigateur ne gère pas le Bluetooth : utiliser Chrome, ou l\'appli installée.');
        appareilWeb = await bt.requestDevice({ acceptAllDevices: true, optionalServices: services });
        const choisie = { id: appareilWeb.id, nom: appareilWeb.name ?? 'Imprimante' };
        await regler(base, 'imprimante', choisie);
        return choisie;
    } catch (e) {
        if (e instanceof ImpressionImpossible) throw e;
        throw new ImpressionImpossible('Aucune imprimante choisie. Allumer l\'imprimante, activer le Bluetooth du téléphone, puis réessayer.');
    }
}

/** Imprime le ticket ; lève ImpressionImpossible avec un message lisible par l'agent. */
export async function imprimer(base: BaseTerrain, ticket: Ticket): Promise<void> {
    const choisie = await imprimanteChoisie(base);
    if (!choisie) throw new ImpressionImpossible('Choisir d\'abord l\'imprimante (Accueil → Imprimante).');
    const donnees = encoderTicket(ticket.lignes);

    if (Capacitor.isNativePlatform()) {
        const ble = await pluginBle();
        try {
            await ble.connect(choisie.id);
            const services = await ble.getServices(choisie.id);
            const cible = trouverEcriture(services.map((s) => ({
                uuid: s.uuid,
                ecritures: s.characteristics.filter((c) => c.properties.write || c.properties.writeWithoutResponse).map((c) => c.uuid),
            })));
            if (!cible) throw new ImpressionImpossible(`« ${choisie.nom} » n'a pas de service d'impression connu (Bluetooth classique seulement ?).`);
            for (const p of paquets(donnees, TAILLE_PAQUET)) {
                await ble.writeWithoutResponse(choisie.id, cible.service, cible.ecriture, new DataView(p.buffer));
                await pause(PAUSE_MS);
            }
        } catch (e) {
            if (e instanceof ImpressionImpossible) throw e;
            throw new ImpressionImpossible(`Impression impossible sur « ${choisie.nom} » : l'allumer, la rapprocher, vérifier le papier.`);
        } finally {
            await ble.disconnect(choisie.id).catch(() => undefined);
        }
        return;
    }

    if (!appareilWeb || appareilWeb.id !== choisie.id) {
        // Chrome ne garde pas l'accès d'une page à l'autre : on redemande.
        await choisirImprimante(base);
    }
    const gatt = await appareilWeb!.gatt?.connect();
    if (!gatt) throw new ImpressionImpossible('Connexion Bluetooth impossible.');
    try {
        const services = await gatt.getPrimaryServices();
        const avecEcritures = await Promise.all(services.map(async (s) => ({
            uuid: s.uuid, service: s,
            caracs: await s.getCharacteristics().catch(() => [] as CaracteristiqueWeb[]),
        })));
        for (const connu of SERVICES_CONNUS) {
            const s = avecEcritures.find((x) => x.uuid === connu.service);
            if (!s) continue;
            const c = await s.service.getCharacteristic(connu.ecriture).catch(() => null);
            if (!c) continue;
            for (const p of paquets(donnees, TAILLE_PAQUET)) {
                await (c.properties.writeWithoutResponse ? c.writeValueWithoutResponse(p) : c.writeValue(p));
                await pause(PAUSE_MS);
            }
            return;
        }
        throw new ImpressionImpossible(`« ${choisie.nom} » n'a pas de service d'impression connu.`);
    } finally {
        gatt.disconnect();
    }
}

/** Premier couple service / caractéristique d'écriture connu parmi ceux de l'appareil. */
export function trouverEcriture(services: { uuid: string; ecritures: string[] }[]): { service: string; ecriture: string } | null {
    const norme = (u: string) => u.toLowerCase();
    for (const connu of SERVICES_CONNUS) {
        const s = services.find((x) => norme(x.uuid) === connu.service);
        if (s?.ecritures.map(norme).includes(connu.ecriture)) {
            return connu;
        }
    }

    return null;
}
