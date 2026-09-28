import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it } from 'vitest';
import { BaseTerrain, regler } from './db';
import { envoyer, HorsReseau, mettreEnFile, SessionExpiree, telechargerReferentiels } from './synchro';

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
        expect(bilan).toEqual({ envoyees: 0, dejaRecues: 5, rejetees: 0 });
        expect(await base.operations.where('statut').equals('envoye').count()).toBe(5);
        expect(serveur.recus.size).toBe(5);
    });

    it('une opération rejetée garde son motif et ne bloque pas les autres', async () => {
        await cinqAchats();
        const serveur = fauxServeur({ rejeter: (d) => (d.poids_brut_g === 300_000 ? 'Prix inférieur au prix officiel' : null) });

        const bilan = await envoyer(base, serveur.f);

        expect(bilan).toEqual({ envoyees: 4, dejaRecues: 0, rejetees: 1 });
        const rejetee = await base.operations.where('statut').equals('rejete').first();
        expect(rejetee?.resume).toBe('Achat 3');
        expect(rejetee?.motif).toContain('prix officiel');
        // Un nouvel envoi ne renvoie pas la rejetée (elle attend une correction).
        expect(await envoyer(base, serveur.f)).toEqual({ envoyees: 0, dejaRecues: 0, rejetees: 0 });
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
});
