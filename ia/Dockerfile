# Service IA de LY AGRICOLE (FastAPI). Le modèle de langage tourne dans le conteneur
# « ollama » voisin (docker-compose.yml) ; aucun appel vers l'extérieur.
FROM python:3.13-slim

WORKDIR /service
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app
COPY tests ./tests
# Outils d'entraînement (sans PyTorch) : testés à la construction ; l'entraînement lui-même
# se fait sur une machine louée avec carte graphique.
COPY entrainement ./entrainement

# Les tests tournent à la construction : une image qui ne rejette plus une dose inventée
# ne doit pas partir en service.
RUN IA_JETON=test IA_LLM=faux python -m unittest discover -s tests -t . -q

RUN useradd --create-home ia
USER ia

EXPOSE 8100
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8100", "--workers", "1"]
