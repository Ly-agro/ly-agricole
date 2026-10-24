"""Rédaction d'un conseil à partir des SEULES fiches validées, puis contrôle.

Le modèle ne voit pas les noms commerciaux ni les doses des fiches : il ne peut pas les
recopier de travers. Il cite les fiches par leur repère ; la plateforme remplace chaque
repère par le texte validé.
"""

from __future__ import annotations

from .controle import controler
from .llm import FauxModele, ModeleLangage
from .modeles import DemandeConseil, ReponseConseil

SYSTEME = """Tu aides les agents de terrain de LY AGRICOLE (Côte d'Ivoire) à rédiger un conseil
pour un producteur. Réponds en français simple, en phrases courtes.

Règles impératives :
1. Tu n'écris JAMAIS de nom de produit, de matière active, ni de dose, ni de quantité.
2. Pour recommander une fiche, écris seulement son repère, par exemple [FICHE-3]. Tu ne peux
   citer que les fiches listées ci-dessous.
3. Ordre : d'abord les pratiques sans produit, puis les solutions biologiques, et un produit
   chimique seulement si une fiche chimique est listée et vraiment nécessaire.
4. Si aucune fiche ne convient, dis que l'agronome donnera son avis. N'invente rien.
5. Ne donne pas de diagnostic : l'observation et le diagnostic éventuel te sont fournis."""


def rediger(demande: DemandeConseil, modele: ModeleLangage) -> ReponseConseil:
    if isinstance(modele, FauxModele):
        modele.fiches = demande.fiches

    lignes = [f"Culture : {demande.culture}", f"Observation de l'agent : {demande.observation}"]
    if demande.diagnostic:
        lignes.append(f"Diagnostic confirmé : {demande.diagnostic}")
    lignes.append("")
    if demande.fiches:
        lignes.append("Fiches validées disponibles :")
        for f in demande.fiches:
            description = f" — {f.description}" if f.description else ""
            lignes.append(f"[FICHE-{f.id}] ({f.type}, cible : {f.cible}) {f.titre}{description}")
    else:
        lignes.append("Aucune fiche validée n'est disponible pour cette culture.")

    texte = modele.generer(SYSTEME, "\n".join(lignes)).strip()
    verdict = controler(texte, demande.fiches, demande.noms_connus)

    return ReponseConseil(
        statut="brouillon" if verdict.accepte else "rejete",
        # Rejeté : le texte est gardé pour qu'un humain voie pourquoi, jamais pour être envoyé.
        texte=texte,
        fiches_citees=verdict.fiches_citees,
        motifs_rejet=verdict.motifs,
        modele=modele.nom,
    )
