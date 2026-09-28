import { v7 as uuidv7 } from 'uuid';
import { type BaseTerrain, type Operation, reglage, regler } from './db';

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
        super('Session expirée ou compte désactivé : se reconnecter.');
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
    const entetes: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json' };
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
    await regler(base, 'serveur', serveur.trim());
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

export async function deconnecter(base: BaseTerrain, f: Fetch = fetch): Promise<void> {
    try {
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
}

/** Télécharge les référentiels : tout la première fois, puis seulement ce qui a changé. */
export async function telechargerReferentiels(base: BaseTerrain, f: Fetch = fetch): Promise<{ complet: boolean; producteurs: number }> {
    const depuis = await reglage<string>(base, 'horodatage');
    const r = (await appeler(base, '/referentiels' + (depuis ? '?depuis=' + encodeURIComponent(depuis) : ''), {}, f)) as Referentiels;

    const tables = [base.villages, base.produits, base.campagnes, base.lots, base.points_collecte, base.producteurs] as const;
    await base.transaction('rw', [...tables, base.comptes, base.prets_en_cours, base.reglages], async () => {
        if (r.complet) {
            await Promise.all(tables.map((t) => t.clear()));
        }
        await base.villages.bulkPut(r.villages as never[]);
        await base.produits.bulkPut(r.produits as never[]);
        await base.campagnes.bulkPut(r.campagnes as never[]);
        await base.lots.bulkPut(r.lots as never[]);
        await base.points_collecte.bulkPut(r.points_collecte as never[]);
        await base.producteurs.bulkPut(r.producteurs as never[]);
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

export interface BilanEnvoi { envoyees: number; dejaRecues: number; rejetees: number }

const PAR_ENVOI = 100;

/**
 * Envoie les opérations en attente, dans l'ordre de saisie, par paquets. Une coupure au
 * milieu laisse le reste en attente ; un renvoi est sans danger (idempotence serveur).
 */
export async function envoyer(base: BaseTerrain, f: Fetch = fetch): Promise<BilanEnvoi> {
    const bilan: BilanEnvoi = { envoyees: 0, dejaRecues: 0, rejetees: 0 };
    const appareil = await appareilId(base);

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
