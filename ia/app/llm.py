"""Modèle de langage : Ollama (local, chez LY) ou un faux modèle pour les essais.

Les données des producteurs ne sortent pas de chez LY : aucun appel à un service externe.
"""

from __future__ import annotations

from typing import Protocol

import httpx

from .modeles import Fiche


class ModeleLangage(Protocol):
    nom: str

    def generer(self, systeme: str, utilisateur: str) -> str: ...


class Ollama:
    def __init__(self, url: str, modele: str, delai_secondes: int) -> None:
        self.url = url.rstrip("/")
        self.nom = f"ollama:{modele}"
        self._modele = modele
        self._delai = delai_secondes

    def generer(self, systeme: str, utilisateur: str) -> str:
        reponse = httpx.post(
            f"{self.url}/api/chat",
            json={
                "model": self._modele,
                "stream": False,
                # Peu de fantaisie : on reformule des fiches, on n'invente pas.
                "options": {"temperature": 0.2},
                "messages": [
                    {"role": "system", "content": systeme},
                    {"role": "user", "content": utilisateur},
                ],
            },
            timeout=self._delai,
        )
        reponse.raise_for_status()
        return str(reponse.json()["message"]["content"])


class FauxModele:
    """
    Répond comme un modèle bien élevé, sans modèle : cite les fiches fournies dans l'ordre
    (pratiques, biologiques, chimiques). Sert aux essais sur le poste de dev et aux tests.
    `reponse_imposee` permet aux tests de simuler un modèle qui désobéit.
    """

    nom = "faux"

    def __init__(self, reponse_imposee: str | None = None) -> None:
        self.reponse_imposee = reponse_imposee
        self.fiches: list[Fiche] = []

    def generer(self, systeme: str, utilisateur: str) -> str:
        if self.reponse_imposee is not None:
            return self.reponse_imposee
        if not self.fiches:
            return ("Aucune fiche validée ne correspond à cette observation. Garder la parcelle propre, "
                    "noter l'évolution et demander l'avis de l'agronome avant tout traitement.")
        ordre = {"pratique": 0, "biologique": 1, "chimique": 2}
        lignes = ["Voici ce que recommandent les fiches validées, dans l'ordre :"]
        for f in sorted(self.fiches, key=lambda f: ordre[f.type]):
            lignes.append(f"- {f.titre} [FICHE-{f.id}]")
        return "\n".join(lignes)
