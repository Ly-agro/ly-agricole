/**
 * Pratiques constatées lors d'une visite : les mêmes codes que App\Enums\PratiqueCulturale
 * (le serveur refuse un code inconnu). Liste commune à tous les produits.
 */
export const PRATIQUES = [
    { code: 'debroussaillage', libelle: 'Débroussaillage' },
    { code: 'pare_feu', libelle: 'Pare-feu' },
    { code: 'taille', libelle: 'Taille' },
    { code: 'fertilisation', libelle: 'Engrais / fumure' },
    { code: 'traitement', libelle: 'Traitement' },
    { code: 'irrigation', libelle: 'Arrosage / irrigation' },
    { code: 'recolte_en_cours', libelle: 'Récolte en cours' },
    { code: 'sechage', libelle: 'Séchage' },
] as const;

export type CodePratique = (typeof PRATIQUES)[number]['code'];

export const MAX_PHOTOS = 10;
