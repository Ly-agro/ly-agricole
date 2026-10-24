import { Capacitor } from '@capacitor/core';
import { type BaseTerrain, reglage, regler } from './db';
import { depuisSaisie } from './mesure';

/**
 * Balance Bluetooth au point de collecte (cahier §10 : « poids sans saisie manuelle »).
 *
 * Tout ce qui se calcule est ici en ENTIERS (grammes, D4) et testé sans matériel : lecture des
 * trames texte des indicateurs de pesage courants, profil standard « Weight Scale », attente d'un
 * poids stable. La partie qui parle au Bluetooth (en bas) est fine et NON VÉRIFIÉE sur une vraie
 * balance : le modèle de balance n'est pas connu (question ouverte n° 38).
 */

export interface Lecture {
    grammes: number;
    /** true / false si la balance le dit (ST / US), null si elle ne le dit pas. */
    stable: boolean | null;
}

const MAX_GRAMMES = 50_000_000; // 50 tonnes : au-delà, c'est une trame mal lue, pas un pesage.

/**
 * Lit UNE trame texte. Formats courants :
 *  - « ST,GS,+  12.50kg » / « US,GS,+  12.50kg » (stable / instable) ;
 *  - « 12.50 kg », « W: 12,5 kg », « +0012.5g » ;
 *  - « 12.500 » sans unité : l'unité choisie à l'appairage s'applique (kg par défaut).
 * Plus de décimales que l'unité n'en porte ⇒ null (on ne devine pas d'arrondi). Négatif ⇒ null.
 */
export function lireTrame(trame: string, uniteParDefaut: 'kg' | 'g' = 'kg'): Lecture | null {
    const texte = trame.trim();
    if (texte === '') {
        return null;
    }

    let stable: boolean | null = null;
    const etat = /^(ST|US)\b/i.exec(texte);
    if (etat) {
        stable = etat[1].toUpperCase() === 'ST';
    }

    const m = /([+-]?)\s*(\d{1,7}(?:[.,]\d{1,4})?)\s*(kg|g)?(?![a-z0-9])/i.exec(etat ? texte.slice(etat[0].length) : texte);
    if (m === null) {
        return null;
    }
    if (m[1] === '-') {
        return null;
    }
    const unite = (m[3]?.toLowerCase() as 'kg' | 'g' | undefined) ?? uniteParDefaut;
    const grammes = depuisSaisie(m[2], unite === 'kg' ? 3 : 0);
    if (grammes === null || grammes > MAX_GRAMMES) {
        return null;
    }

    return { grammes, stable };
}

/** Coupe un flux d'octets en trames (fin de ligne \r et/ou \n) ; garde le reste pour plus tard. */
export class LecteurTrames {
    private reste = '';

    pousser(morceau: string): string[] {
        this.reste += morceau;
        const lignes = this.reste.split(/\r\n|\r|\n/);
        this.reste = lignes.pop() ?? '';
        // Un flux sans fin de ligne ne doit pas grossir sans limite.
        if (this.reste.length > 256) {
            this.reste = '';
        }

        return lignes.filter((l) => l.trim() !== '');
    }
}

/**
 * Attend un poids STABLE : la balance le dit (ST) ; sinon, `nbLectures` lectures identiques de
 * suite. Une lecture qui change ou « instable » (US) repart de zéro. Un poids nul ou négatif
 * n'est jamais « stable » (balance vide).
 */
export class Stabilisateur {
    private dernier: number | null = null;
    private serie = 0;

    constructor(private readonly nbLectures = 5) {}

    ajouter(lecture: Lecture): { grammes: number; stable: boolean } {
        if (lecture.stable === false || lecture.grammes !== this.dernier) {
            this.serie = 1;
        } else {
            this.serie++;
        }
        this.dernier = lecture.grammes;

        const stable = lecture.grammes > 0 && (lecture.stable === true || this.serie >= this.nbLectures);

        return { grammes: lecture.grammes, stable };
    }

    reinitialiser(): void {
        this.dernier = null;
        this.serie = 0;
    }
}

/**
 * Profil Bluetooth standard « Weight Scale » (service 0x181D, mesure 0x2A9D) : indicateur, 1 octet
 * de drapeaux puis le poids sur 2 octets (little-endian). En unités SI, l'unité est 0,005 kg = 5 g.
 * Unités impériales (livres) ⇒ null : les livres ne se convertissent pas sans arrondi. 0xFFFF =
 * « mesure non aboutie » ⇒ null.
 */
export function mesureStandard(octets: Uint8Array): number | null {
    if (octets.length < 3) {
        return null;
    }
    if ((octets[0] & 0x01) !== 0) {
        return null;
    }
    const brut = octets[1] | (octets[2] << 8);
    if (brut === 0xffff) {
        return null;
    }

    return brut * 5;
}

/** 500250 → « 500,25 » ; 500000 → « 500 » ; 250 → « 0,25 ». Pour remplir un champ en kg. */
export function grammesEnKgTexte(grammes: number): string {
    const kilos = Math.trunc(grammes / 1000);
    const reste = String(grammes % 1000).padStart(3, '0').replace(/0+$/, '');

    return reste === '' ? String(kilos) : `${kilos},${reste}`;
}

/**
 * D'où vient le poids qu'on enregistre ? « balance » seulement si le champ contient EXACTEMENT
 * ce que la balance a donné ; tout autre poids (tapé, ou modifié après coup) est « manuel ».
 * Le serveur garde cette mention : un poids tapé à la main alors qu'une balance existe se voit.
 */
export function sourcePoids(saisie: string, priseDeLaBalance: number | null): 'balance' | 'manuel' {
    if (priseDeLaBalance === null) {
        return 'manuel';
    }

    return depuisSaisie(saisie, 3) === priseDeLaBalance ? 'balance' : 'manuel';
}

// ---------------------------------------------------------------------------------------------
// Bluetooth (NON VÉRIFIÉ sur une vraie balance — question ouverte n° 38)
// ---------------------------------------------------------------------------------------------

export type TypeService = 'standard' | 'texte';

/** Services de notification des balances / indicateurs courants. */
export const SERVICES_BALANCE: { service: string; notification: string; type: TypeService }[] = [
    { service: '0000181d-0000-1000-8000-00805f9b34fb', notification: '00002a9d-0000-1000-8000-00805f9b34fb', type: 'standard' },
    { service: '0000ffe0-0000-1000-8000-00805f9b34fb', notification: '0000ffe1-0000-1000-8000-00805f9b34fb', type: 'texte' },
    { service: '6e400001-b5a3-f393-e0a9-e50e24dcca9e', notification: '6e400003-b5a3-f393-e0a9-e50e24dcca9e', type: 'texte' },
    { service: '49535343-fe7d-4ae5-8fa9-9fafd205e455', notification: '49535343-1e4d-4bd9-ba61-23c647249616', type: 'texte' },
];

export interface BalanceChoisie { id: string; nom: string; unite: 'kg' | 'g' }

export class BalanceIndisponible extends Error {}

/** Premier service connu parmi ceux de l'appareil (qui expose bien la caractéristique de notification). */
export function trouverNotification(services: { uuid: string; notifications: string[] }[]): { service: string; notification: string; type: TypeService } | null {
    const norme = (u: string) => u.toLowerCase();
    for (const connu of SERVICES_BALANCE) {
        const s = services.find((x) => norme(x.uuid) === connu.service);
        if (s?.notifications.map(norme).includes(connu.notification)) {
            return connu;
        }
    }

    return null;
}

export async function balanceChoisie(base: BaseTerrain): Promise<BalanceChoisie | undefined> {
    return reglage<BalanceChoisie>(base, 'balance');
}

/** Transforme ce que la balance envoie en lectures (une ou plusieurs par notification). */
export function decoderNotification(type: TypeService, octets: Uint8Array, lecteur: LecteurTrames, unite: 'kg' | 'g'): Lecture[] {
    if (type === 'standard') {
        const g = mesureStandard(octets);

        return g === null ? [] : [{ grammes: g, stable: null }];
    }

    return lecteur.pousser(new TextDecoder('ascii').decode(octets))
        .map((t) => lireTrame(t, unite))
        .filter((l): l is Lecture => l !== null);
}

async function pluginBle() {
    const { BleClient } = await import('@capacitor-community/bluetooth-le');
    await BleClient.initialize();

    return BleClient;
}

interface AppareilWeb { id: string; name?: string; gatt?: { connect(): Promise<ServeurWeb> } }
interface ServeurWeb { getPrimaryServices(): Promise<{ uuid: string; getCharacteristic(u: string): Promise<CaracWeb> }[]>; disconnect(): void }
interface CaracWeb {
    startNotifications(): Promise<CaracWeb>;
    stopNotifications(): Promise<CaracWeb>;
    addEventListener(t: string, f: (e: { target: { value?: DataView } }) => void): void;
    removeEventListener(t: string, f: (e: { target: { value?: DataView } }) => void): void;
}

let appareilWeb: AppareilWeb | null = null;

/** L'agent choisit la balance une fois ; elle est gardée sur le téléphone, avec son unité. */
export async function choisirBalance(base: BaseTerrain, unite: 'kg' | 'g' = 'kg'): Promise<BalanceChoisie> {
    const services = SERVICES_BALANCE.map((s) => s.service);
    try {
        if (Capacitor.isNativePlatform()) {
            const ble = await pluginBle();
            const a = await ble.requestDevice({ optionalServices: services });
            const choisie = { id: a.deviceId, nom: a.name ?? 'Balance', unite };
            await regler(base, 'balance', choisie);

            return choisie;
        }
        const bt = (navigator as unknown as { bluetooth?: { requestDevice(o: object): Promise<AppareilWeb> } }).bluetooth;
        if (!bt) throw new BalanceIndisponible('Ce navigateur ne gère pas le Bluetooth : utiliser Chrome, ou l\'appli installée.');
        appareilWeb = await bt.requestDevice({ acceptAllDevices: true, optionalServices: services });
        const choisie = { id: appareilWeb.id, nom: appareilWeb.name ?? 'Balance', unite };
        await regler(base, 'balance', choisie);

        return choisie;
    } catch (e) {
        if (e instanceof BalanceIndisponible) throw e;
        throw new BalanceIndisponible('Aucune balance choisie. Allumer la balance, activer le Bluetooth du téléphone, puis réessayer.');
    }
}

/**
 * Écoute la balance et rend chaque poids, avec sa stabilité, à `surLecture`. Rend une fonction
 * qui arrête l'écoute. Lève BalanceIndisponible avec un message lisible par l'agent.
 */
export async function ecouterBalance(
    base: BaseTerrain,
    surLecture: (poids: { grammes: number; stable: boolean }) => void,
): Promise<() => Promise<void>> {
    const choisie = await balanceChoisie(base);
    if (!choisie) throw new BalanceIndisponible('Choisir d\'abord la balance.');
    const stabilisateur = new Stabilisateur();
    const lecteur = new LecteurTrames();
    const recevoir = (type: TypeService, octets: Uint8Array) => {
        for (const l of decoderNotification(type, octets, lecteur, choisie.unite)) {
            surLecture(stabilisateur.ajouter(l));
        }
    };

    if (Capacitor.isNativePlatform()) {
        const ble = await pluginBle();
        try {
            await ble.connect(choisie.id);
            const services = await ble.getServices(choisie.id);
            const cible = trouverNotification(services.map((s) => ({
                uuid: s.uuid,
                notifications: s.characteristics.filter((c) => c.properties.notify || c.properties.indicate).map((c) => c.uuid),
            })));
            if (!cible) throw new BalanceIndisponible(`« ${choisie.nom} » n'envoie pas son poids par un service connu (Bluetooth classique seulement ?).`);
            await ble.startNotifications(choisie.id, cible.service, cible.notification, (v: DataView) => {
                recevoir(cible.type, new Uint8Array(v.buffer, v.byteOffset, v.byteLength));
            });

            return async () => {
                await ble.stopNotifications(choisie.id, cible.service, cible.notification).catch(() => undefined);
                await ble.disconnect(choisie.id).catch(() => undefined);
            };
        } catch (e) {
            await ble.disconnect(choisie.id).catch(() => undefined);
            if (e instanceof BalanceIndisponible) throw e;
            throw new BalanceIndisponible(`Impossible de lire « ${choisie.nom} » : l'allumer, la rapprocher, vérifier le Bluetooth.`);
        }
    }

    if (!appareilWeb || appareilWeb.id !== choisie.id) {
        await choisirBalance(base, choisie.unite);
    }
    const gatt = await appareilWeb!.gatt?.connect();
    if (!gatt) throw new BalanceIndisponible('Connexion Bluetooth impossible.');
    for (const connu of SERVICES_BALANCE) {
        const s = (await gatt.getPrimaryServices()).find((x) => x.uuid === connu.service);
        if (!s) continue;
        const c = await s.getCharacteristic(connu.notification).catch(() => null);
        if (!c) continue;
        const ecouteur = (e: { target: { value?: DataView } }) => {
            const v = e.target.value;
            if (v) recevoir(connu.type, new Uint8Array(v.buffer, v.byteOffset, v.byteLength));
        };
        c.addEventListener('characteristicvaluechanged', ecouteur);
        await c.startNotifications();

        return async () => {
            c.removeEventListener('characteristicvaluechanged', ecouteur);
            await c.stopNotifications().catch(() => undefined);
            gatt.disconnect();
        };
    }
    gatt.disconnect();
    throw new BalanceIndisponible(`« ${choisie.nom} » n'envoie pas son poids par un service connu.`);
}
