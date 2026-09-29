import { v7 as uuidv7 } from 'uuid';
import { type BaseTerrain, type Operation, reglage, regler } from './db';
import { verifierAdresse } from './serveur';

/**
 * Échanges avec le serveur (contrat : skill terrain-hors-ligne, /api/*).
 *
 * `navigator.onLine === true` ne prouve rien : on TENTE l'envoi, et un échec réseau
 * laisse simplement les opérations « en attente ». Un envoi arrivé au serveur mais dont
 * la réponse s'est perdue sera renvoyé : le serveur répond alors « deja_recu ».
 */

export class HorsReseau extends Error {
    constructor() {
        super('Serveur injoignable : rien n\'est perdu, les saisies restent sur le téléphone.');
    }
}

export class SessionExpiree extends Error {
    constructor() {
        super('Session expirée, compte désactivé ou appareil coupé par le bureau : se reconnecter.');
    }
}

export class RefusServeur extends Error {}

type Fetch = typeof fetch;

const DELAI_MS = 20_000;

async function appeler(base: BaseTerrain, chemin: string, options: RequestInit = {}, f: Fetch = fetch): Promise<unknown> {
    const serveur = await reglage<string>(base, 'serveur');
    if (!serveur) {
        throw new SessionExpiree();
    }
    const jeton = await reglage<string>(base, 'jeton');
    const entetes: Record<string, string> = { Accept: 'application/json' };
    if (!(options.body instanceof FormData)) {
        entetes['Content-Type'] = 'application/json';
    }
    if (jeton) {
        entetes.Authorization = `Bearer ${jeton}`;
    }

    let reponse: Response;
    try {
        reponse = await f(serveur.replace(/\/+$/, '') + '/api' + chemin, {
            ...options,
            headers: entetes,
            signal: AbortSignal.timeout(DELAI_MS),
        });
    } catch {
        throw new HorsReseau();
    }

    if (reponse.status === 401) {
        throw new SessionExpiree();
    }
    const corps = await reponse.json().catch(() => null);
    if (!reponse.ok) {
        const erreurs = (corps as { errors?: Record<string, string[]>; message?: string } | null);
        const detail = erreurs?.errors ? Object.values(erreurs.errors).flat().join(' ') : erreurs?.message;
        throw new RefusServeur(detail || `Le serveur a répondu ${reponse.status}.`);
    }

    return corps;
}

export async function appareilId(base: BaseTerrain): Promise<string> {
    let id = await reglage<string>(base, 'appareil_id');
    if (!id) {
        id = uuidv7();
        await regler(base, 'appareil_id', id);
    }

    return id;
}

export interface Utilisateur { id: number; nom: string; role: string }

export async function connecter(base: BaseTerrain, serveur: string, email: string, motDePasse: string, f: Fetch = fetch): Promise<Utilisateur> {
    const adresse = verifierAdresse(serveur);
    if ('erreur' in adresse) {
        throw new RefusServeur(adresse.erreur);
    }
    await regler(base, 'serveur', adresse.adresse);
    await base.reglages.delete('jeton');
    const appareil = await appareilId(base);
    const r = (await appeler(base, '/connexion', {
        method: 'POST',
        body: JSON.stringify({ email: email.trim(), password: motDePasse, appareil: `LY Terrain ${appareil.slice(-8)}` }),
    }, f)) as { jeton: string; utilisateur: Utilisateur };

    const precedent = await reglage<Utilisateur>(base, 'utilisateur');
    // Un autre agent sur le même téléphone : ses référentiels, pas ceux du précédent.
    // (La file d'envoi, elle, n'est jamais vidée ici.)
    if (precedent && precedent.id !== r.utilisateur.id) {
        await base.reglages.delete('horodatage');
    }
    await regler(base, 'jeton', r.jeton);
    await regler(base, 'utilisateur', r.utilisateur);

    return r.utilisateur;
}

/** Déclare au bureau le jeton Firebase du téléphone (notifications) ; gardé pour le retirer. */
export async function declarerJetonPush(base: BaseTerrain, jeton: string, appareil: string, f: Fetch = fetch): Promise<void> {
    await appeler(base, '/push', { method: 'POST', body: JSON.stringify({ jeton, appareil }) }, f);
    await regler(base, 'jeton_push', jeton);
}

export async function deconnecter(base: BaseTerrain, f: Fetch = fetch): Promise<void> {
    try {
        // Un téléphone déconnecté ne doit plus recevoir les avis de cet utilisateur.
        const jetonPush = await reglage<string>(base, 'jeton_push');
        if (jetonPush) {
            await appeler(base, '/push', { method: 'DELETE', body: JSON.stringify({ jeton: jetonPush }) }, f);
            await base.reglages.delete('jeton_push');
        }
        await appeler(base, '/deconnexion', { method: 'POST' }, f);
    } catch {
        // Hors réseau : le jeton est oublié ici ; le bureau peut désactiver le compte.
    }
    await base.reglages.delete('jeton');
}

interface Referentiels {
    horodatage: string;
    complet: boolean;
    villages: object[];
    produits: object[];
    campagnes: object[];
    lots: object[];
    points_collecte: object[];
    comptes: object[];
    producteurs: object[];
    prets_en_cours: object[];
    categories_depense: object[];
    parcelles?: object[];
}

/** Télécharge les référentiels : tout la première fois, puis seulement ce qui a changé. */
export async function telechargerReferentiels(base: BaseTerrain, f: Fetch = fetch): Promise<{ complet: boolean; producteurs: number }> {
    const depuis = await reglage<string>(base, 'horodatage');
    const r = (await appeler(base, '/referentiels' + (depuis ? '?depuis=' + encodeURIComponent(depuis) : ''), {}, f)) as Referentiels;

    const tables = [base.villages, base.produits, base.campagnes, base.lots, base.points_collecte, base.producteurs, base.categories_depense, base.parcelles] as const;
    await base.transaction('rw', [...tables, base.comptes, base.prets_en_cours, base.reglages, base.operations], async () => {
        if (r.complet) {
            await Promise.all(tables.map((t) => t.clear()));
        }
        await base.villages.bulkPut(r.villages as never[]);
        await base.produits.bulkPut(r.produits as never[]);
        await base.campagnes.bulkPut(r.campagnes as never[]);
        await base.lots.bulkPut(r.lots as never[]);
        await base.points_collecte.bulkPut(r.points_collecte as never[]);
        await base.producteurs.bulkPut(r.producteurs as never[]);
        await base.categories_depense.bulkPut((r.categories_depense ?? []) as never[]);
        await base.parcelles.bulkPut((r.parcelles ?? []) as never[]);
        // Fiches créées sur le téléphone et pas encore au bureau : elles restent utilisables.
        const locales = await base.operations.filter((o) => o.type === 'producteur' && o.statut !== 'envoye' && o.statut !== 'abandonne').toArray();
        for (const o of locales) {
            if (!(await base.producteurs.get(o.uuid))) {
                const d = o.donnees as { nom: string; prenoms: string; telephone: string | null; village_id: number };
                await base.producteurs.put({ id: o.uuid, code: null, nom: d.nom, prenoms: d.prenoms, telephone: d.telephone, village_id: d.village_id, groupe_id: null, actif: true });
            }
        }
        // Idem pour les parcelles relevées ici : on peut les visiter avant l'envoi.
        const parcellesLocales = await base.operations.filter((o) => o.type === 'parcelle' && o.statut !== 'envoye' && o.statut !== 'abandonne').toArray();
        for (const o of parcellesLocales) {
            if (!(await base.parcelles.get(o.uuid))) {
                const d = o.donnees as { producteur_id: string; nom: string; produit_id?: number | null };
                await base.parcelles.put({ id: o.uuid, producteur_id: d.producteur_id, nom: d.nom, surface_m2: null, produit_id: d.produit_id ?? null, actif: true });
            }
        }
        // Toujours complets : ils changent sans date (soldes, restants dus).
        await base.comptes.clear();
        await base.comptes.bulkPut(r.comptes as never[]);
        await base.prets_en_cours.clear();
        await base.prets_en_cours.bulkPut(r.prets_en_cours as never[]);
        await regler(base, 'horodatage', r.horodatage);
    });

    return { complet: r.complet, producteurs: r.producteurs.length };
}

/** Met une saisie dans la file. L'UUID v7 est créé ICI : c'est la clé d'idempotence. */
export async function mettreEnFile(base: BaseTerrain, type: Operation['type'], donnees: Record<string, unknown>, resume: string): Promise<Operation> {
    const operation: Operation = {
        uuid: uuidv7(),
        type,
        cree_at: new Date().toISOString(),
        donnees,
        statut: 'en_attente',
        motif: null,
        envoye_at: null,
        resume,
    };
    await base.operations.add(operation);

    return operation;
}

export interface BilanEnvoi { envoyees: number; dejaRecues: number; rejetees: number; photos: number }

const PAR_ENVOI = 100;

/**
 * Envoie les opérations en attente, dans l'ordre de saisie, par paquets. Une coupure au
 * milieu laisse le reste en attente ; un renvoi est sans danger (idempotence serveur).
 */
export async function envoyer(base: BaseTerrain, f: Fetch = fetch): Promise<BilanEnvoi> {
    const bilan: BilanEnvoi = { envoyees: 0, dejaRecues: 0, rejetees: 0, photos: 0 };
    const appareil = await appareilId(base);

    // Les photos d'abord : une dépense ou une visite exige que ses photos soient déjà au bureau.
    bilan.photos = await envoyerPhotos(base, appareil, f);

    for (;;) {
        const paquet = (await base.operations.where('statut').equals('en_attente').sortBy('uuid')).slice(0, PAR_ENVOI);
        if (paquet.length === 0) {
            return bilan;
        }

        const r = (await appeler(base, '/sync', {
            method: 'POST',
            body: JSON.stringify({
                appareil_id: appareil,
                operations: paquet.map(({ uuid, type, cree_at, donnees }) => ({ uuid, type, cree_at, donnees })),
            }),
        }, f)) as { resultats: { uuid: string; statut: string; motif?: string }[] };

        const maintenant = new Date().toISOString();
        await base.transaction('rw', base.operations, async () => {
            for (const res of r.resultats) {
                if (res.statut === 'accepte' || res.statut === 'deja_recu') {
                    await base.operations.update(res.uuid, { statut: 'envoye', motif: null, envoye_at: maintenant });
                    res.statut === 'accepte' ? bilan.envoyees++ : bilan.dejaRecues++;
                } else {
                    await base.operations.update(res.uuid, { statut: 'rejete', motif: res.motif ?? 'Rejetée sans motif.' });
                    bilan.rejetees++;
                }
            }
        });

        // Garde-fou : une réponse qui ne traite aucune opération du paquet arrêterait la boucle.
        const traites = new Set(r.resultats.map((x) => x.uuid));
        if (!paquet.some((o) => traites.has(o.uuid))) {
            throw new RefusServeur('Réponse du serveur incomplète : réessayer plus tard.');
        }
    }
}

/**
 * Envoie les photos en attente, une par une (réseau faible). Une photo refusée (pas une
 * image, trop lourde) est marquée « rejete » ; l'opération qui la référence sera
 * rejetée par le serveur avec son motif. Envoyée : le fichier est retiré du téléphone.
 */
async function envoyerPhotos(base: BaseTerrain, appareil: string, f: Fetch): Promise<number> {
    let envoyees = 0;
    const photos = await base.photos.where('statut').equals('en_attente').sortBy('uuid');
    for (const photo of photos) {
        const corps = new FormData();
        corps.append('uuid', photo.uuid);
        corps.append('appareil_id', appareil);
        corps.append('prise_at', photo.prise_at);
        if (photo.lat !== null && photo.lng !== null) {
            corps.append('lat', String(photo.lat));
            corps.append('lng', String(photo.lng));
        }
        corps.append('fichier', photo.blob, photo.uuid + '.jpg');

        try {
            await appeler(base, '/photos', { method: 'POST', body: corps }, f);
            await base.photos.update(photo.uuid, { statut: 'envoye', motif: null, blob: new Blob([]) });
            envoyees++;
        } catch (e) {
            if (!(e instanceof RefusServeur)) {
                throw e;
            }
            await base.photos.update(photo.uuid, { statut: 'rejete', motif: e.message });
        }
    }

    return envoyees;
}

/** Remet une opération rejetée dans la file, avec le MÊME UUID (corrigée si besoin). */
export async function renvoyer(base: BaseTerrain, uuid: string, corrections: Record<string, unknown> = {}): Promise<void> {
    const op = await base.operations.get(uuid);
    if (!op || op.statut !== 'rejete') {
        return;
    }
    await base.operations.update(uuid, { statut: 'en_attente', motif: null, donnees: { ...op.donnees, ...corrections } });
}

/** Laisse une opération rejetée de côté : gardée pour la trace, plus jamais envoyée. */
export async function abandonner(base: BaseTerrain, uuid: string): Promise<void> {
    const op = await base.operations.get(uuid);
    if (op?.statut === 'rejete') {
        await base.operations.update(uuid, { statut: 'abandonne' });
    }
}
