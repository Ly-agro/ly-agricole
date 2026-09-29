import { type BaseTerrain, type Operation, reglage } from './db';
import type { Utilisateur } from './synchro';
import { bonDePesee, type Ticket } from './ticket';

/**
 * Bon de pesée d'un achat gardé dans la file : tout vient de la base du téléphone, pour
 * imprimer sans réseau, juste après la pesée ou plus tard (réimpression).
 */
export async function bonDepuisOperation(base: BaseTerrain, op: Operation): Promise<Ticket | null> {
    if (op.type !== 'achat') {
        return null;
    }
    const d = op.donnees as {
        lot_id: number; campagne_id: number; producteur_id: string; date_achat: string;
        poids_brut_g: number; tare_g: number; humidite_pour_mille: number | null; prix_kg_fcfa: number;
        pret_id: string | null; grammes_rembourses: number;
    };
    const [producteur, lot, campagne, pret, agent] = await Promise.all([
        base.producteurs.get(d.producteur_id),
        base.lots.get(d.lot_id),
        base.campagnes.get(d.campagne_id),
        d.pret_id ? base.prets_en_cours.get(d.pret_id) : Promise.resolve(undefined),
        reglage<Utilisateur>(base, 'utilisateur'),
    ]);
    const produit = campagne ? await base.produits.get(campagne.produit_id) : undefined;

    return bonDePesee({
        uuid: op.uuid,
        date: new Date(d.date_achat),
        fournisseur: producteur ? `${producteur.nom} ${producteur.prenoms}` : 'Producteur',
        carte: producteur?.code ?? null,
        agent: agent?.nom ?? '—',
        produit: produit?.nom ?? 'Produit',
        campagne: campagne?.code ?? '—',
        lot: lot?.code ?? '—',
        poids_brut_g: d.poids_brut_g,
        tare_g: d.tare_g,
        humidite_pour_mille: d.humidite_pour_mille,
        kor_centieme_lbs: null,
        grainage_noix_kg: null,
        prix_kg_fcfa: d.prix_kg_fcfa,
        pret: d.pret_id && d.grammes_rembourses > 0 ? { reference: pret?.reference ?? 'prêt', grammes: d.grammes_rembourses } : null,
    });
}
