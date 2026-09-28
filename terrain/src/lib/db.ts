import Dexie, { type EntityTable } from 'dexie';

/**
 * Base locale du téléphone (IndexedDB). Deux sortes de données :
 * - les référentiels reçus du serveur (remplacés ou complétés à chaque téléchargement) ;
 * - la FILE D'ENVOI : chaque saisie est une opération à UUID v7, gardée jusqu'à ce
 *   que le serveur l'ait acceptée — et même après, pour que l'agent voie ce qui est parti.
 */

export interface Village { id: number; zone_id: number; nom: string; actif: boolean }
export interface Produit { id: number; code: string; nom: string; actif: boolean }
export interface Campagne { id: number; produit_id: number; code: string; statut: string; prix_officiel_kg_fcfa: number | null; actif: boolean }
export interface Lot { id: number; code: string; produit_id: number; campagne_id: number; magasin_id: number; statut: string; actif: boolean }
export interface PointCollecte { id: number; village_id: number; nom: string; actif: boolean }
export interface Compte { id: number; nom: string; type: string; titulaire_id: number | null; actif: boolean }
export interface Producteur { id: string; code: string | null; nom: string; prenoms: string; telephone: string | null; village_id: number; groupe_id: number | null; actif: boolean }
export interface PretEnCours { id: string; reference: string; producteur_id: string; campagne_id: number; restant_du_fcfa: number }

export interface CategorieDepense { id: number; nom: string; exclue_fonds_campagne: boolean; actif: boolean }

/** `abandonne` : rejetée et laissée de côté par l'agent (gardée pour la trace). */
export type StatutOperation = 'en_attente' | 'envoye' | 'rejete' | 'abandonne';

export interface Operation {
    uuid: string;
    type: 'achat' | 'producteur' | 'parcelle' | 'depense';
    /** Heure du téléphone (peut être fausse ; le serveur garde aussi la sienne). */
    cree_at: string;
    donnees: Record<string, unknown>;
    statut: StatutOperation;
    motif: string | null;
    envoye_at: string | null;
    /** Ligne lisible pour l'écran « À envoyer ». */
    resume: string;
}

/** Photo prise sur le terrain, compressée, envoyée À PART des opérations. */
export interface Photo {
    uuid: string;
    blob: Blob;
    prise_at: string;
    lat: number | null;
    lng: number | null;
    statut: 'en_attente' | 'envoye' | 'rejete';
    motif: string | null;
}

/** Réglages : serveur, jeton, utilisateur, horodatage du dernier téléchargement. */
export interface Reglage { cle: string; valeur: unknown }

export class BaseTerrain extends Dexie {
    reglages!: EntityTable<Reglage, 'cle'>;
    villages!: EntityTable<Village, 'id'>;
    produits!: EntityTable<Produit, 'id'>;
    campagnes!: EntityTable<Campagne, 'id'>;
    lots!: EntityTable<Lot, 'id'>;
    points_collecte!: EntityTable<PointCollecte, 'id'>;
    comptes!: EntityTable<Compte, 'id'>;
    producteurs!: EntityTable<Producteur, 'id'>;
    prets_en_cours!: EntityTable<PretEnCours, 'id'>;
    operations!: EntityTable<Operation, 'uuid'>;
    categories_depense!: EntityTable<CategorieDepense, 'id'>;
    photos!: EntityTable<Photo, 'uuid'>;

    constructor(nom = 'ly-terrain') {
        super(nom);
        this.version(1).stores({
            reglages: 'cle',
            villages: 'id',
            produits: 'id',
            campagnes: 'id',
            lots: 'id, campagne_id',
            points_collecte: 'id',
            comptes: 'id',
            producteurs: 'id, code, nom',
            prets_en_cours: 'id, producteur_id',
            // uuid v7 : l'ordre des clés est l'ordre de saisie.
            operations: 'uuid, statut',
        });
        // Semaine 9 : dépenses terrain et photos. (Ne jamais modifier une version publiée.)
        this.version(2).stores({
            categories_depense: 'id',
            photos: 'uuid, statut',
        }).upgrade(async (tx) => {
            // Nouvelle table de référentiel : un delta n'y mettrait que les lignes
            // modifiées depuis. Le prochain téléchargement doit être complet.
            await tx.table('reglages').delete('horodatage');
        });
    }
}

export const db = new BaseTerrain();

export async function reglage<T>(base: BaseTerrain, cle: string): Promise<T | undefined> {
    return (await base.reglages.get(cle))?.valeur as T | undefined;
}

export async function regler(base: BaseTerrain, cle: string, valeur: unknown): Promise<void> {
    await base.reglages.put({ cle, valeur });
}
