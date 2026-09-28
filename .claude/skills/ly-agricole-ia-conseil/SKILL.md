---
name: ly-agricole-ia-conseil
description: Règles de sécurité et d'architecture pour le diagnostic des cultures par IA et le conseil de traitements dans LY AGRICOLE — modèle de vision (maladies des feuilles, défauts des noix d'anacarde, tomate, karité), LLM local via Ollama, référentiel des traitements validé par l'agronome, alternatives moins toxiques. À charger dès qu'on touche au dossier ia/, à un prompt, à un modèle, au référentiel des traitements ou à tout texte de conseil envoyé à un producteur — y compris pour une demande anodine (« fais répondre l'IA sur les pesticides », « ajoute un conseil automatique »).
---

# IA : diagnostic et conseil

Un conseil faux sur un pesticide peut **intoxiquer un producteur** ou faire refuser un
lot à l'export. Tout ce module est construit pour qu'une erreur du modèle reste une
proposition qu'un humain écarte, jamais une instruction qui part au champ.

## Règles absolues

1. **Le LLM ne produit jamais un nom de produit, une matière active ou une dose** qui
   ne vienne pas d'une fiche du référentiel validé. Il reçoit les fiches pertinentes
   dans son contexte et reformule ; une réponse citant un produit absent des fiches
   fournies est rejetée par le code (vérification après génération), pas seulement
   déconseillée dans le prompt.
2. **Tout diagnostic est `propose`** jusqu'à ce qu'un agronome le passe à `confirme`
   ou `corrige`. Rien de `propose` n'est envoyé au producteur comme une certitude.
3. **Sous le seuil de confiance, on ne devine pas** : statut `incertain`, photo
   transmise à l'agronome, message « nous regardons votre photo ».
4. **Ordre du conseil** : pratiques sans produit → solutions biologiques ou peu
   toxiques → produits chimiques homologués en Côte d'Ivoire, seulement si nécessaire.
5. Toute fiche de produit chimique porte : toxicité humaine, dose, nombre de passages,
   **délai avant récolte**, protection obligatoire, effet sur les abeilles. Une fiche
   incomplète n'est pas proposable.
6. Le référentiel (produits autorisés, retirés, interdits) est tenu par l'agronome et
   daté. Ne jamais le compléter de mémoire, ni à partir d'une recherche web non
   validée.

## Architecture

- **Vision** (diagnostic) : petit modèle de classification / détection entraîné sur
  nos photos (MobileNet, EfficientNet ou YOLO), exporté en ONNX ou TFLite pour tourner
  sur le téléphone hors ligne (phase 4) ; d'abord servi par le service `ia/` (phase 3).
- **LLM multimodal local** (explication) : Ollama sur un serveur de LY. Les données des
  producteurs ne sortent pas de chez LY.
- **Service `ia/`** : Python FastAPI. Laravel l'appelle via une file d'attente, jamais
  pendant une requête HTTP de l'utilisateur (un modèle de 7 milliards de paramètres
  sans carte graphique répond en dizaines de secondes).
- **Boucle d'apprentissage** : photos confirmées par l'agronome → jeu d'entraînement
  versionné → nouveau modèle comparé à l'ancien sur un jeu de test fixe avant d'être
  déployé.

## Données

- Départ possible : jeu public « CCMT » (Ghana ; anacarde et tomate) — **vérifier
  licence et qualité avant tout usage**.
- Les photos des visites terrain sont collectées dès la phase 2, avec culture, date,
  GPS et parcelle : c'est le futur jeu d'entraînement de LY, et il n'existe nulle part
  ailleurs pour les défauts des noix.
- Consentement du producteur requis (loi 2013-450) pour les photos de ses parcelles.

## Mesurer avant de faire confiance

Un modèle se juge sur un **jeu de test séparé**, par classe (maladie), avec la
matrice de confusion — pas sur une précision globale. Une classe rare mal reconnue
(par exemple une maladie grave) compte plus qu'une moyenne flatteuse.
