"""Réglages du service, lus dans l'environnement (fichier .env via docker compose)."""

from __future__ import annotations

import os
from dataclasses import dataclass


def _env(cle: str, defaut: str | None = None) -> str | None:
    # Une variable déclarée vide vaut « absente » (même piège que dans Laravel).
    valeur = os.environ.get(cle)
    return defaut if valeur is None or valeur.strip() == "" else valeur.strip()


@dataclass(frozen=True)
class Reglages:
    jeton: str | None
    llm: str
    ollama_url: str
    ollama_modele: str
    delai_secondes: int
    modele_vision: str | None
    seuil_confiance_pour_mille: int


def reglages() -> Reglages:
    return Reglages(
        # Sans jeton, le service refuse tout (il ne s'ouvre jamais par défaut).
        jeton=_env("IA_JETON"),
        # « ollama » en production ; « faux » pour les essais sans modèle (poste de dev).
        llm=_env("IA_LLM", "ollama") or "ollama",
        ollama_url=_env("OLLAMA_URL", "http://ollama:11434") or "",
        ollama_modele=_env("OLLAMA_MODELE", "qwen2.5:7b-instruct") or "",
        delai_secondes=int(_env("IA_DELAI_SECONDES", "180") or "180"),
        # Chemin d'un modèle de vision entraîné (ONNX). Absent : tout diagnostic est « incertain ».
        modele_vision=_env("IA_MODELE_VISION"),
        # Sous ce seuil, on ne devine pas (règle 3).
        seuil_confiance_pour_mille=int(_env("IA_SEUIL_CONFIANCE", "850") or "850"),
    )
