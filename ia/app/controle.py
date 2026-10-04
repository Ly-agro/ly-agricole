"""Contrôle APRÈS génération (skill ly-agricole-ia-conseil, règle 1).

Le modèle de langage ne doit jamais écrire lui-même un nom de produit, une matière active
ou une dose. Il ne désigne un traitement que par un repère ``[FICHE-n]`` ; c'est la
plateforme qui remplace le repère par le texte VALIDÉ de la fiche (nom, dose, délai avant
récolte, protection). Toute réponse qui s'écarte de cette règle est REJETÉE ici, par le
code — le prompt seul ne suffit pas.

Limite assumée : on ne peut pas reconnaître un nom de produit que l'on ne connaît pas.
C'est pourquoi une réponse acceptée reste un BROUILLON qu'un humain relit, jamais un
conseil envoyé tel quel à un producteur.
"""

from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass, field

from .modeles import Fiche

REPERE = re.compile(r"\[FICHE-(\d+)\]", re.IGNORECASE)

# Une dose : un nombre suivi d'une unité de quantité (ml, l, g, kg, cl, cc…), avec ou sans
# « par hectare / litre / pied ». Les pourcentages et les jours ne sont pas des doses.
DOSE = re.compile(
    r"\d+(?:[.,]\d+)?\s*(?:ml|millilitres?|cl|l|litres?|g|grammes?|kg|kilos?|cc|mg)\b(?:\s*(?:/|par)\s*\w+)?",
    re.IGNORECASE,
)

# Familles de produits chimiques : permises seulement quand une fiche chimique validée
# est fournie ET citée (sinon le modèle « conseille » un produit sans fiche).
FAMILLES_CHIMIQUES = (
    "insecticide", "fongicide", "herbicide", "nematicide", "acaricide", "pesticide",
    "produit chimique", "traitement chimique", "desherbant chimique",
)


def sans_accents(texte: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", texte.lower()) if unicodedata.category(c) != "Mn")


@dataclass
class Verdict:
    accepte: bool
    fiches_citees: list[int] = field(default_factory=list)
    motifs: list[str] = field(default_factory=list)


def controler(texte: str, fiches: list[Fiche], noms_connus: list[str]) -> Verdict:
    """
    :param fiches: fiches fournies au modèle pour cette demande (les seules citables).
    :param noms_connus: TOUS les noms commerciaux et matières actives du référentiel, y
        compris retirés ou interdits : aucun ne doit apparaître en clair.
    """
    motifs: list[str] = []
    fournies = {f.id: f for f in fiches}

    citees: list[int] = []
    for m in REPERE.finditer(texte):
        n = int(m.group(1))
        if n not in fournies:
            motifs.append(f"Repère [FICHE-{n}] inconnu : seule une fiche fournie peut être citée.")
        elif n not in citees:
            citees.append(n)

    # Hors des repères, aucun nom ni aucune dose.
    hors_reperes = REPERE.sub(" ", texte)
    normalise = sans_accents(hors_reperes)

    for dose in DOSE.findall(hors_reperes):
        motifs.append(f"Dose écrite par le modèle (« {dose.strip()} ») : une dose ne vient que d'une fiche validée.")

    for nom in {n.strip() for n in noms_connus if n and len(n.strip()) >= 3}:
        if re.search(r"(?<!\w)" + re.escape(sans_accents(nom)) + r"(?!\w)", normalise):
            motifs.append(f"Nom de produit ou de matière active écrit en clair (« {nom} ») : citer la fiche par son repère.")

    chimiques_citees = [n for n in citees if fournies[n].type == "chimique"]
    for famille in FAMILLES_CHIMIQUES:
        if famille in normalise and not chimiques_citees:
            motifs.append(f"« {famille} » évoqué sans fiche chimique validée citée.")

    # Ordre du conseil (règle 4) : un produit chimique ne vient qu'après les pratiques et les
    # solutions biologiques fournies ; on vérifie qu'elles sont citées, et AVANT lui.
    if chimiques_citees:
        premier_chimique = min(citees.index(n) for n in chimiques_citees)
        for f in fiches:
            if f.type in ("pratique", "biologique"):
                if f.id not in citees:
                    motifs.append(f"Fiche {f.type} [FICHE-{f.id}] non citée alors qu'un produit chimique l'est.")
                elif citees.index(f.id) > premier_chimique:
                    motifs.append(f"Fiche {f.type} [FICHE-{f.id}] citée après un produit chimique.")

    # Une fiche chimique incomplète n'est jamais proposable (règle 5) : défense en profondeur.
    for n in chimiques_citees:
        if not fournies[n].complete():
            motifs.append(f"Fiche chimique [FICHE-{n}] incomplète : elle n'est pas proposable.")

    return Verdict(accepte=not motifs, fiches_citees=citees, motifs=motifs)
