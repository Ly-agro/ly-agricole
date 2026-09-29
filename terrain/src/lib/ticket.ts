import { fcfa, kg, montantAchat } from './mesure';

/**
 * Ticket 58 mm (32 colonnes), même mise en page que App\Support\Ticket58 côté serveur.
 * Composé SUR LE TÉLÉPHONE, hors ligne, à partir de la saisie : le bon se remet au
 * producteur au moment de la pesée, avant tout envoi au bureau.
 */

export const LARGEUR = 32;

export interface LigneTicket { texte: string; gras: boolean; centre: boolean; grand: boolean }

function couper(texte: string, largeur = LARGEUR): string[] {
    const propre = texte.replace(/\s+/g, ' ').trim();
    if (propre === '') return [''];
    const lignes: string[] = [];
    let courante = '';
    for (let mot of propre.split(' ')) {
        while (mot.length > largeur) {
            if (courante !== '') {
                lignes.push(courante);
                courante = '';
            }
            lignes.push(mot.slice(0, largeur));
            mot = mot.slice(largeur);
        }
        const essai = courante === '' ? mot : `${courante} ${mot}`;
        if (essai.length <= largeur) {
            courante = essai;
        } else {
            lignes.push(courante);
            courante = mot;
        }
    }
    lignes.push(courante);

    return lignes;
}

export class Ticket {
    readonly lignes: LigneTicket[] = [];

    private ajouter(texte: string, gras = false, centre = false, grand = false): this {
        this.lignes.push({ texte, gras, centre, grand });
        return this;
    }

    titre(texte: string): this {
        couper(texte, LARGEUR / 2).forEach((l) => this.ajouter(l, true, true, true));
        return this;
    }

    centre(texte: string, gras = false): this {
        couper(texte).forEach((l) => this.ajouter(l, gras, true));
        return this;
    }

    texte(texte: string, gras = false): this {
        couper(texte).forEach((l) => this.ajouter(l, gras));
        return this;
    }

    /** Libellé à gauche, valeur calée à droite ; la valeur passe dessous si trop longue. */
    paire(libelle: string, valeur: string, gras = false): this {
        const place = LARGEUR - valeur.length - 1;
        if (place < 8) {
            this.texte(libelle, gras);
            return this.ajouter(valeur.padStart(LARGEUR), gras);
        }
        const morceaux = couper(libelle, place);
        morceaux.slice(0, -1).forEach((l) => this.ajouter(l, gras));
        const dernier = morceaux[morceaux.length - 1];

        return this.ajouter(dernier + ' '.repeat(LARGEUR - dernier.length - valeur.length) + valeur, gras);
    }

    trait(motif = '-'): this {
        return this.ajouter(motif.repeat(LARGEUR));
    }

    vide(): this {
        return this.ajouter('');
    }

    signature(qui: string): this {
        return this.vide().vide().texte(`${qui} : ${'_'.repeat(Math.max(4, LARGEUR - qui.length - 3))}`);
    }

    enTexte(): string {
        return this.lignes
            .map((l) => (l.centre ? ' '.repeat(Math.floor(Math.max(0, (l.grand ? LARGEUR / 2 : LARGEUR) - l.texte.length) / 2)) + l.texte : l.texte))
            .join('\n');
    }
}

export interface DonneesBonAchat {
    uuid: string;
    date: Date;
    fournisseur: string;
    carte: string | null;
    agent: string;
    produit: string;
    campagne: string;
    lot: string;
    poids_brut_g: number;
    tare_g: number;
    humidite_pour_mille: number | null;
    kor_centieme_lbs: number | null;
    grainage_noix_kg: number | null;
    prix_kg_fcfa: number;
    /** Kilos retenus pour rembourser un prêt (mêmes règles que la page d'achat). */
    pret: { reference: string; grammes: number } | null;
}

/**
 * Bon de pesée remis sur place. Le bureau revalide tout (seuil, prix, prêt) : ce bon
 * est PROVISOIRE et le dit ; la référence définitive (ACH-…) arrive après l'envoi.
 */
export function bonDePesee(d: DonneesBonAchat): Ticket {
    const net = d.poids_brut_g - d.tare_g;
    const montant = montantAchat(net, d.prix_kg_fcfa);
    const especes = montantAchat(net - (d.pret?.grammes ?? 0), d.prix_kg_fcfa);
    const date = d.date.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    const t = new Ticket()
        .titre('LY AGRICOLE')
        .centre('Cultiver – Élever – Durer')
        .trait('=')
        .centre('BON DE PESÉE', true)
        .centre(`Réf. provisoire ${d.uuid.slice(-8).toUpperCase()}`)
        .centre(`du ${date}`)
        .trait('=')
        .texte(`Fournisseur : ${d.fournisseur}`, true);
    if (d.carte) t.texte(`Carte ${d.carte}`);
    t.texte(`${d.produit} — campagne ${d.campagne}`)
        .texte(`Lot ${d.lot}`)
        .texte(`Acheteur : ${d.agent}`)
        .trait()
        .paire('Poids brut', kg(d.poids_brut_g))
        .paire('Tare (sacs)', kg(d.tare_g))
        .paire('Poids net', kg(net), true);
    if (d.humidite_pour_mille !== null) t.paire('Humidité', `${Math.trunc(d.humidite_pour_mille / 10)},${d.humidite_pour_mille % 10} %`);
    if (d.kor_centieme_lbs !== null) t.paire('KOR (lbs/80 kg)', `${Math.trunc(d.kor_centieme_lbs / 100)},${String(d.kor_centieme_lbs % 100).padStart(2, '0')}`);
    if (d.grainage_noix_kg !== null) t.paire('Grainage (noix/kg)', String(d.grainage_noix_kg));
    t.trait()
        .paire('Prix', `${fcfa(d.prix_kg_fcfa)}/kg`)
        .paire('Montant', fcfa(montant), true);
    if (d.pret) t.paire(`Retenu prêt ${d.pret.reference} (${kg(d.pret.grammes)})`, `- ${fcfa(montant - especes)}`);

    return t.paire('Payé en espèces', fcfa(especes), true)
        .trait('*')
        .texte('Bon provisoire : le bureau vérifie la pesée et le paiement. Un SMS confirme le montant retenu.')
        .trait('*')
        .signature('Fournisseur')
        .signature('Acheteur')
        .vide();
}
