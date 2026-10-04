import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it } from 'vitest';
import { BaseTerrain, regler } from './db';
import { telechargerReferentiels } from './synchro';

let base: BaseTerrain;
let numero = 0;

beforeEach(async () => {
    base = new BaseTerrain('test-langue-' + numero++);
    await regler(base, 'serveur', 'http://serveur.test');
    await regler(base, 'jeton', 'jeton-test');
});

const vide = { villages: [], produits: [], campagnes: [], lots: [], points_collecte: [], comptes: [], producteurs: [], prets_en_cours: [], categories_depense: [] };
const reponse = (extra: object, complet = true) => (async () => new Response(JSON.stringify({ horodatage: '2027-01-11T10:00:00Z', complet, ...vide, ...extra }), { status: 200 })) as unknown as typeof fetch;

describe('langues du téléphone', () => {
    it('la base locale est en version 4 avec sa table de langues', () => {
        expect(base.verno).toBe(4);
        expect(base.tables.map((t) => t.name)).toContain('langues');
    });

    it('garde les langues reçues, avec celles désactivées (marquées actif = false)', async () => {
        await telechargerReferentiels(base, reponse({ langues: [{ id: 1, code: 'dyu', nom: 'Dioula', actif: true }, { id: 2, code: 'sef', nom: 'Sénoufo', actif: false }] }));

        expect(await base.langues.count()).toBe(2);
        expect((await base.langues.get(2))?.actif).toBe(false);
    });

    it('un téléchargement complet remet la liste du serveur, un delta la complète', async () => {
        await telechargerReferentiels(base, reponse({ langues: [{ id: 1, code: 'dyu', nom: 'Dioula', actif: true }, { id: 2, code: 'sef', nom: 'Sénoufo', actif: true }] }));
        await telechargerReferentiels(base, reponse({ langues: [{ id: 2, code: 'sef', nom: 'Sénoufo', actif: false }] }, false));
        expect(await base.langues.count()).toBe(2);

        await telechargerReferentiels(base, reponse({ langues: [{ id: 1, code: 'dyu', nom: 'Dioula', actif: true }] }));
        expect(await base.langues.count()).toBe(1);
    });

    it('un serveur sans « langues » (ancienne version) ne casse pas le téléchargement', async () => {
        await telechargerReferentiels(base, reponse({}));

        expect(await base.langues.count()).toBe(0);
    });
});
