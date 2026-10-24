"""Outils de l'entraînement SANS PyTorch (testés sur le poste de dev).

Lecture des jeux (CCMT, export LY), correspondance des classes (docs/DONNEES_IA.md §2),
répartition entraînement / test FIXE, mesures par classe et comparaison à l'ancien modèle.
"""

from __future__ import annotations

import csv
import hashlib
import unicodedata
from dataclasses import dataclass
from pathlib import Path

EXTENSIONS = {".jpg", ".jpeg", ".png"}
PART_TEST = 10  # une photo sur dix au jeu de test (même règle que ia:exporter-jeu)

CULTURES = {"cashew": "anacarde", "anacarde": "anacarde", "tomato": "tomate", "tomate": "tomate"}

# Noms de dossiers des jeux publics → classe LY. Une classe absente arrête le script :
# on ne devine pas une maladie.
CLASSES = {
    "anthracnose": "anthracnose",
    "gumosis": "gommose", "gummosis": "gommose", "gommose": "gommose",
    "leaf-miner": "mineuse", "leafminer": "mineuse", "mineuse": "mineuse",
    "red-rust": "rouille-rouge", "rouille-rouge": "rouille-rouge",
    "healthy": "sain", "sain": "sain",
    "septoria-leaf-spot": "septoriose", "septoriose": "septoriose",
    "leaf-curl": "enroulement-feuilles", "enroulement-feuilles": "enroulement-feuilles",
    "leaf-blight": "brulure-feuilles", "brulure-feuilles": "brulure-feuilles",
    "verticillium-wilt": "verticilliose", "verticilliose": "verticilliose",
}


def normaliser(nom: str) -> str:
    sans = "".join(c for c in unicodedata.normalize("NFD", nom.lower()) if unicodedata.category(c) != "Mn")
    for s in (" ", "_"):
        sans = sans.replace(s, "-")
    while "--" in sans:
        sans = sans.replace("--", "-")
    return sans.strip("-")


def jeu_fixe(cle: str) -> str:
    """Toujours le même jeu pour la même photo, d'un export et d'une machine à l'autre."""
    return "test" if int(hashlib.md5(cle.encode()).hexdigest()[:6], 16) % PART_TEST == 0 else "entrainement"


@dataclass(frozen=True)
class Exemple:
    chemin: Path
    culture: str
    classe: str
    jeu: str
    source: str


class ClasseInconnue(Exception):
    pass


def lire_ccmt(racine: Path, culture: str) -> list[Exemple]:
    """Dossiers <Culture>/<classe>/*.jpg (jeu brut CCMT) ; autres cultures ignorées."""
    exemples: list[Exemple] = []
    for dossier_culture in sorted(p for p in racine.rglob("*") if p.is_dir() and CULTURES.get(normaliser(p.name)) == culture):
        for dossier_classe in sorted(p for p in dossier_culture.iterdir() if p.is_dir()):
            classe = CLASSES.get(normaliser(dossier_classe.name))
            if classe is None:
                raise ClasseInconnue(f"Classe CCMT inconnue : « {dossier_classe.name} » ({dossier_culture.name}). "
                                     "L'ajouter à CLASSES après avis, ne pas deviner.")
            for photo in sorted(dossier_classe.iterdir()):
                if photo.suffix.lower() in EXTENSIONS:
                    relatif = photo.relative_to(racine).as_posix()
                    exemples.append(Exemple(photo, culture, classe, jeu_fixe("ccmt:" + relatif), "ccmt"))
    return exemples


def lire_export_ly(racine: Path, culture: str, avec_provisoires: bool = False) -> list[Exemple]:
    """manifeste.csv de `php artisan ia:exporter-jeu` (jeu déjà fixé par la plateforme)."""
    exemples: list[Exemple] = []
    with open(racine / "manifeste.csv", encoding="utf-8") as f:
        for ligne in csv.DictReader(f, delimiter=";"):
            if ligne["culture"] != culture:
                continue
            provisoire = ligne.get("source", "agronome").startswith("provisoire")
            if provisoire and not avec_provisoires:
                continue
            if ligne["classe"] not in set(CLASSES.values()):
                raise ClasseInconnue(f"Classe LY inconnue : « {ligne['classe']} » ({ligne['fichier']}).")
            exemples.append(Exemple(racine / ligne["fichier"], culture, ligne["classe"],
                                    "entrainement" if provisoire else ligne["jeu"], "ly-provisoire" if provisoire else "ly"))
    return exemples


def mesures(vrais: list[str], predits: list[str], classes: list[str]) -> dict:
    """Rappel, précision et matrice de confusion PAR CLASSE (jamais une seule moyenne)."""
    index = {c: i for i, c in enumerate(classes)}
    matrice = [[0] * len(classes) for _ in classes]
    for v, p in zip(vrais, predits):
        matrice[index[v]][index[p]] += 1
    par_classe = {}
    for c, i in index.items():
        effectif = sum(matrice[i])
        predits_c = sum(ligne[i] for ligne in matrice)
        bons = matrice[i][i]
        par_classe[c] = {
            "effectif_test": effectif,
            "rappel": round(bons / effectif, 4) if effectif else None,
            "precision": round(bons / predits_c, 4) if predits_c else None,
        }
    rappels = [m["rappel"] for m in par_classe.values() if m["rappel"] is not None]
    return {
        "classes": classes,
        "par_classe": par_classe,
        "rappel_moyen": round(sum(rappels) / len(rappels), 4) if rappels else None,
        "matrice_confusion": matrice,
    }


def comparer(nouveau: dict, ancien: dict | None, tolerance: float = 0.03, effectif_min: int = 20) -> tuple[bool, list[str]]:
    """
    Le nouveau modèle ne remplace l'ancien que s'il ne recule sur AUCUNE maladie (au-delà de
    la tolérance) et que son rappel moyen ne baisse pas. Une classe rare mal reconnue compte
    plus qu'une moyenne flatteuse (skill ly-agricole-ia-conseil).
    """
    motifs: list[str] = []
    for c, m in nouveau["par_classe"].items():
        if m["effectif_test"] < effectif_min:
            motifs.append(f"« {c} » : {m['effectif_test']} photo(s) de test seulement (moins de {effectif_min}) : résultat peu fiable.")
    if ancien is None:
        return (not motifs, motifs or ["Premier modèle : pas d'ancien à comparer."])

    for c, m in nouveau["par_classe"].items():
        avant = ancien["par_classe"].get(c, {}).get("rappel")
        if avant is not None and m["rappel"] is not None and m["rappel"] < avant - tolerance:
            motifs.append(f"« {c} » recule : rappel {m['rappel']:.2f} contre {avant:.2f} avant.")
    if ancien.get("rappel_moyen") is not None and nouveau["rappel_moyen"] is not None and nouveau["rappel_moyen"] < ancien["rappel_moyen"]:
        motifs.append(f"Rappel moyen en baisse : {nouveau['rappel_moyen']:.2f} contre {ancien['rappel_moyen']:.2f}.")
    return (not motifs, motifs or ["Meilleur ou égal sur chaque maladie : peut remplacer l'ancien."])
