"""Données échangées avec la plateforme (Laravel). Tout vient du référentiel validé."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field


class Fiche(BaseModel):
    """Fiche du référentiel des traitements, validée et datée par l'agronome."""

    id: int
    type: Literal["pratique", "biologique", "chimique"]
    cible: str = Field(description="Maladie ou ravageur visé, ou « général »")
    titre: str = Field(description="Intitulé court, sans nom commercial ni dose")
    description: str = ""
    # Champs obligatoires d'une fiche chimique (règle 5).
    nom_commercial: str | None = None
    matiere_active: str | None = None
    dose: str | None = None
    passages: int | None = None
    delai_avant_recolte_jours: int | None = None
    toxicite_humaine: str | None = None
    protection: str | None = None
    effet_abeilles: str | None = None

    def complete(self) -> bool:
        if self.type != "chimique":
            return True
        return all(
            v not in (None, "")
            for v in (
                self.nom_commercial, self.matiere_active, self.dose, self.passages,
                self.delai_avant_recolte_jours, self.toxicite_humaine, self.protection, self.effet_abeilles,
            )
        )


class DemandeConseil(BaseModel):
    culture: str
    observation: str = Field(max_length=2000, description="Ce que l'agent a constaté, sans donnée personnelle")
    diagnostic: str | None = Field(default=None, description="Maladie retenue, si un humain l'a confirmée")
    fiches: list[Fiche] = []
    noms_connus: list[str] = Field(default=[], description="Tous les noms du référentiel, y compris retirés et interdits")


class ReponseConseil(BaseModel):
    statut: Literal["brouillon", "rejete"]
    texte: str
    fiches_citees: list[int]
    motifs_rejet: list[str]
    modele: str


class ReponseDiagnostic(BaseModel):
    statut: Literal["propose", "incertain"]
    classe: str | None
    confiance_pour_mille: int
    motif: str
    modele: str
