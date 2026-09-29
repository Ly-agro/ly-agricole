"""Diagnostic d'une photo (maladie, défaut des noix).

Aucun modèle de vision n'est encore entraîné : il faut d'abord des photos confirmées par un
agronome (boucle d'apprentissage, skill ly-agricole-ia-conseil). En attendant, tout
diagnostic est « incertain » : la photo va à l'humain, on ne devine pas (règle 3).
"""

from __future__ import annotations

from .config import Reglages
from .modeles import ReponseDiagnostic


def diagnostiquer(photo: bytes, culture: str, reglages: Reglages) -> ReponseDiagnostic:
    if not photo:
        return ReponseDiagnostic(statut="incertain", classe=None, confiance_pour_mille=0,
                                 motif="Photo vide.", modele="aucun")

    if reglages.modele_vision is None:
        return ReponseDiagnostic(
            statut="incertain", classe=None, confiance_pour_mille=0,
            motif="Aucun modèle de vision entraîné pour l'instant : la photo est transmise à un humain.",
            modele="aucun",
        )

    # Branchement du modèle ONNX entraîné (phase suivante) : classe + confiance, et
    # « incertain » sous le seuil. Tant qu'il n'est pas écrit, on reste prudent.
    return ReponseDiagnostic(
        statut="incertain", classe=None, confiance_pour_mille=0,
        motif=f"Modèle « {reglages.modele_vision} » configuré mais pas encore branché : diagnostic humain.",
        modele=reglages.modele_vision,
    )
