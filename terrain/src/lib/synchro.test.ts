import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it } from 'vitest';
import { BaseTerrain, regler } from './db';
import { abandonner, connecter, envoyer, HorsReseau, mettreEnFile, renvoyer, SessionExpiree, telechargerReferentiels } from './synchro';

let base: BaseTerrain;
let numero = 0;

beforeEach(async () => {
    base = new BaseTerrain('test-' + numero++);
    await regler(base, 'serveur', 'http://serveur.test');
    await regler(base, 'jeton', 'jeton-test');
});

/** Faux serveur idempotent : un UUID accepté une fois répond ensuite « deja_recu ». */
function fauxServeur(options: { couperApresTraitement?: number; rejeter?: (d: Record<string, unknown>) => string | null } = {}) {
    const recus = new Set<string>();
    let appels = 0;
    const f = (async (_url: string, init?: RequestInit) => {
        appels++;
        const corps = JSON.parse(String(init?.body)) as { operations: { uuid: string; donnees: Record<string, unknown> }[] };
        const resultats = corps.operations.map((o) => {
            if (recus.has(o.uuid)) {
                return { uuid: o.uuid, statut: 'deja_recu' };
            }
            const motif = options.rejeter?.(o.donnees) ?? null;
            if (motif) {
                return { uuid: o.uuid, statut: 'rejete', motif };
            }
            recus.add(o.uuid);

            return { uuid: o.uuid, statut: 'accepte' };
        });
        if (options.couperApresTraitement === appels) {
            // Le serveur a tout enregistré, mais la réponse n'arrive jamais.
            throw new TypeError('Failed to fetch');
        }

        return new Response(JSON.stringify({ resultats }), { status: 200 });
    }) as typeof fetch;

    return { f, recus };
}

async function cinqAchats(): Promise<void> {
    for (let i = 1; i <= 5; i++) {
        await mettreEnFile(base, 'achat', { poids_brut_g: i * 100_000, tare_g: 0 }, `Achat ${i}`);
    }
}

describe('file d\'envoi', () => {
    it('hors réseau : rien ne part, rien n\'est perdu', async () => {
        await cinqAchats();
        const horsReseau = (async () => {
            throw new TypeError('Failed to fetch');
        }) as typeof fetch;

        await expect(envoyer(base, horsReseau)).rejects.toBeInstanceOf(HorsReseau);
        expect(await base.operations.where('statut').equals('en_attente').count()).toBe(5);
    });

    it('réponse perdue puis renvoi : tout est envoyé, le serveur ne reçoit chaque achat qu\'une fois', async () => {
        await cinqAchats();
        const serveur = fauxServeur({ couperApresTraitement: 1 });

        await expect(envoyer(base, serveur.f)).rejects.toBeInstanceOf(HorsReseau);
        expect(await base.operations.where('statut').equals('en_attente').count()).toBe(5);

        const bilan = await envoyer(base, serveur.f);
        expect(bilan).toEqual({ envoyees: 0, dejaRecues: 5, rejetees: 0, photos: 0 });
        expect(await base.operations.where('statut').equals('envoye').count()).toBe(5);
        expect(serveur.recus.size).toBe(5);
    });

    it('une opération rejetée garde son motif et ne bloque pas les autres', async () => {
        await cinqAchats();
        const serveur = fauxServeur({ rejeter: (d) => (d.poids_brut_g === 300_000 ? 'Prix inférieur au prix officiel' : null) });

        const bilan = await envoyer(base, serveur.f);

        expect(bilan).toEqual({ envoyees: 4, dejaRecues: 0, rejetees: 1, photos: 0 });
        const rejetee = await base.operations.where('statut').equals('rejete').first();
        expect(rejetee?.resume).toBe('Achat 3');
        expect(rejetee?.motif).toContain('prix officiel');
        // Un nouvel envoi ne renvoie pas la rejetée (elle attend une correction).
        expect(await envoyer(base, serveur.f)).toEqual({ envoyees: 0, dejaRecues: 0, rejetees: 0, photos: 0 });
    });

    it('les opérations partent dans l\'ordre de saisie (UUID v7)', async () => {
        await cinqAchats();
        const ordre: string[] = [];
        const f = (async (_u: string, init?: RequestInit) => {
            const corps = JSON.parse(String(init?.body)) as { operations: { uuid: string; donnees: { poids_brut_g: number } }[] };
            ordre.push(...corps.operations.map((o) => String(o.donnees.poids_brut_g)));

            return new Response(JSON.stringify({ resultats: corps.operations.map((o) => ({ uuid: o.uuid, statut: 'accepte' })) }));
        }) as typeof fetch;

        await envoyer(base, f);
        expect(ordre).toEqual(['100000', '200000', '300000', '400000', '500000']);
    });

    it('401 : session expirée, les opérations restent en attente', async () => {
        await cinqAchats();
        const f = (async () => new Response('{}', { status: 401 })) as typeof fetch;

        await expect(envoyer(base, f)).rejects.toBeInstanceOf(SessionExpiree);
        expect(await base.operations.where('statut').equals('en_attente').count()).toBe(5);
    });
});

describe('photos et rejets (semaine 9)', () => {
    it('les photos partent avant les opérations, puis leur fichier quitte le téléphone', async () => {
        const ordre: string[] = [];
        await base.photos.add({ uuid: '01900000-0000-7000-8000-000000000001', blob: new Blob(['jpeg']), prise_at: 't', lat: 9.4, lng: -5.6, statut: 'en_attente', motif: null });
        await mettreEnFile(base, 'depense', { justificatif_photo: '01900000-0000-7000-8000-000000000001' }, 'Dépense');
        const f = (async (url: string, init?: RequestInit) => {
            ordre.push(url.endsWith('/photos') ? 'photo' : 'sync');
            if (url.endsWith('/photos')) {
                expect(init?.body).toBeInstanceOf(FormData);
                expect((init?.headers as Record<string, string>)['Content-Type']).toBeUndefined();

                return new Response(JSON.stringify({ statut: 'accepte' }), { status: 201 });
            }
            const corps = JSON.parse(String(init?.body)) as { operations: { uuid: string }[] };

            return new Response(JSON.stringify({ resultats: corps.operations.map((o) => ({ uuid: o.uuid, statut: 'accepte' })) }));
        }) as typeof fetch;

        const bilan = await envoyer(base, f);

        expect(ordre).toEqual(['photo', 'sync']);
        expect(bilan.photos).toBe(1);
        const photo = await base.photos.get('01900000-0000-7000-8000-000000000001');
        expect(photo?.statut).toBe('envoye');
        expect(photo?.blob.size).toBe(0);
    });

    it('une photo refusée ne bloque pas l\'envoi ; coupure pendant les photos : rien ne part', async () => {
        await base.photos.add({ uuid: 'p1', blob: new Blob(['x']), prise_at: 't', lat: null, lng: null, statut: 'en_attente', motif: null });
        const refus = (async (url: string) => url.endsWith('/photos')
            ? new Response(JSON.stringify({ message: 'Le fichier doit être une image.' }), { status: 422 })
            : new Response(JSON.stringify({ resultats: [] }))) as typeof fetch;
        await envoyer(base, refus);
        expect((await base.photos.get('p1'))?.motif).toContain('image');

        await base.photos.add({ uuid: 'p2', blob: new Blob(['x']), prise_at: 't', lat: null, lng: null, statut: 'en_attente', motif: null });
        await mettreEnFile(base, 'achat', {}, 'Achat');
        const coupure = (async () => {
            throw new TypeError('Failed to fetch');
        }) as typeof fetch;
        await expect(envoyer(base, coupure)).rejects.toBeInstanceOf(HorsReseau);
        expect(await base.operations.where('statut').equals('en_attente').count()).toBe(1);
    });

    it('rejet : renvoyer avec le même UUID (doublon confirmé) ou abandonner', async () => {
        const op = await mettreEnFile(base, 'producteur', { nom: 'A' }, 'Producteur A');
        const autre = await mettreEnFile(base, 'achat', {}, 'Achat');
        await base.operations.update(op.uuid, { statut: 'rejete', motif: 'À confirmer : même téléphone.' });
        await base.operations.update(autre.uuid, { statut: 'rejete', motif: 'Prix inférieur' });

        await renvoyer(base, op.uuid, { doublons_confirmes: true });
        await abandonner(base, autre.uuid);

        const renvoye = await base.operations.get(op.uuid);
        expect(renvoye?.statut).toBe('en_attente');
        expect(renvoye?.donnees).toEqual({ nom: 'A', doublons_confirmes: true });
        expect((await base.operations.get(autre.uuid))?.statut).toBe('abandonne');
    });

    it('connexion : un serveur en http public est refusé (question 27)', async () => {
        await expect(connecter(base, 'http://gestion.ly-agricole.ci', 'a@b.c', 'x')).rejects.toThrow('https');
    });
});

describe('référentiels', () => {
    const reponse = (corps: object) => (async () => new Response(JSON.stringify(corps))) as typeof fetch;
    const vide = { villages: [], produits: [], campagnes: [], lots: [], points_collecte: [], comptes: [], prets_en_cours: [] };

    it('complet remplace, delta complète, prêts toujours remplacés', async () => {
        await telechargerReferentiels(base, reponse({ ...vide, horodatage: 't1', complet: true,
            producteurs: [{ id: 'a', nom: 'A' }, { id: 'b', nom: 'B' }],
            prets_en_cours: [{ id: 'p1', producteur_id: 'a', restant_du_fcfa: 100 }] }));
        await telechargerReferentiels(base, reponse({ ...vide, horodatage: 't2', complet: false,
            producteurs: [{ id: 'c', nom: 'C' }], prets_en_cours: [] }));

        expect((await base.producteurs.toArray()).map((p) => p.id).sort()).toEqual(['a', 'b', 'c']);
        expect(await base.prets_en_cours.count()).toBe(0);
        expect((await base.reglages.get('horodatage'))?.valeur).toBe('t2');
    });

    it('une base v1 déjà remplie repart sur un téléchargement complet en passant en v2', async () => {
        const nom = 'migration-' + numero++;
        const { default: Dexie } = await import('dexie');
        const v1 = new Dexie(nom);
        v1.version(1).stores({ reglages: 'cle', operations: 'uuid, statut' });
        await v1.table('reglages').put({ cle: 'horodatage', valeur: 't1' });
        await v1.table('operations').put({ uuid: 'u1', statut: 'en_attente' });
        v1.close();

        const v2 = new BaseTerrain(nom);
        expect(await v2.reglages.get('horodatage')).toBeUndefined();
        expect(await v2.operations.count()).toBe(1); // la file n'est jamais touchée
    });

    it('un téléchargement complet garde les producteurs créés sur le téléphone et pas encore envoyés', async () => {
        const op = await mettreEnFile(base, 'producteur', { nom: 'Koné', prenoms: 'Awa', telephone: null, village_id: 1 }, 'Koné Awa');

        await telechargerReferentiels(base, reponse({ ...vide, horodatage: 't1', complet: true, producteurs: [{ id: 'a', nom: 'A' }] }));

        expect((await base.producteurs.get(op.uuid))?.nom).toBe('Koné');
        expect(await base.producteurs.count()).toBe(2);
    });
});
