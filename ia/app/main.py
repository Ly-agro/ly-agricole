"""Service IA de LY AGRICOLE (FastAPI). Appelé par la plateforme depuis sa file d'attente,
jamais pendant une requête d'utilisateur : un modèle local peut mettre des dizaines de
secondes à répondre.

    GET  /sante       état du service (sans jeton)
    POST /conseil     brouillon de conseil à partir des fiches validées (+ contrôle)
    POST /diagnostic  diagnostic d'une photo (incertain tant qu'aucun modèle n'est entraîné)
"""

from __future__ import annotations

import hmac

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile

from .config import Reglages, reglages
from .conseil import rediger
from .diagnostic import diagnostiquer
from .llm import FauxModele, ModeleLangage, Ollama
from .modeles import DemandeConseil, ReponseConseil, ReponseDiagnostic

app = FastAPI(title="LY AGRICOLE — service IA", version="0.1.0")

TAILLE_MAX_PHOTO = 8 * 1024 * 1024


def exiger_jeton(x_jeton_ia: str | None = Header(default=None)) -> Reglages:
    r = reglages()
    # Fermé par défaut : sans jeton configuré, personne n'entre.
    if r.jeton is None or x_jeton_ia is None or not hmac.compare_digest(r.jeton, x_jeton_ia):
        raise HTTPException(status_code=401, detail="Jeton du service IA absent ou incorrect.")
    return r


def modele_de_langage(r: Reglages) -> ModeleLangage:
    if r.llm == "faux":
        return FauxModele()
    return Ollama(r.ollama_url, r.ollama_modele, r.delai_secondes)


@app.get("/sante")
def sante() -> dict[str, object]:
    r = reglages()
    return {
        "service": "ok",
        "llm": r.llm if r.llm == "faux" else f"ollama:{r.ollama_modele}",
        "vision": r.modele_vision or "aucun modèle entraîné",
        "jeton_configure": r.jeton is not None,
    }


@app.post("/conseil", response_model=ReponseConseil)
def conseil(demande: DemandeConseil, r: Reglages = Depends(exiger_jeton)) -> ReponseConseil:
    try:
        return rediger(demande, modele_de_langage(r))
    except Exception as e:  # modèle injoignable, délai dépassé…
        raise HTTPException(status_code=503, detail=f"Modèle de langage indisponible : {e}") from e


@app.post("/diagnostic", response_model=ReponseDiagnostic)
async def diagnostic(
    photo: UploadFile = File(...),
    culture: str = Form(...),
    r: Reglages = Depends(exiger_jeton),
) -> ReponseDiagnostic:
    contenu = await photo.read(TAILLE_MAX_PHOTO + 1)
    if len(contenu) > TAILLE_MAX_PHOTO:
        raise HTTPException(status_code=413, detail="Photo trop lourde (8 Mo au plus).")
    return diagnostiquer(contenu, culture, r)
