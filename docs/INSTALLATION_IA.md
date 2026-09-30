# Installer le serveur IA de LY AGRICOLE

Ce serveur fait tourner le modèle de langage (Ollama) et le service `ia/` (FastAPI). Il
est **chez LY** : les photos de parcelles et les données des producteurs n'en sortent pas
(loi 2013-450, skill `ly-agricole-ia-conseil`). La plateforme (Laravel), elle, peut être
hébergée en ligne : elle joint le serveur IA par un tunnel VPN.

```
Plateforme en ligne (HTTPS)  ──VPN WireGuard──►  Serveur IA chez LY
   file d'attente (queue:work)                   ├─ service ia/ (port 8100, VPN seulement)
                                                 └─ Ollama (réseau interne Docker)
```

## 1. Matériel conseillé

| | Minimum qui marche bien | Plus confortable |
| --- | --- | --- |
| Carte graphique | NVIDIA RTX 3060 **12 Go** | RTX 4060 Ti 16 Go ou RTX 3090 24 Go |
| Mémoire | 32 Go | 64 Go |
| Processeur | 6 à 8 cœurs récents | 8 cœurs ou plus |
| Disques | SSD NVMe 1 To + un 2ᵉ disque pour les sauvegardes | 2 To |
| Courant | **onduleur 1 500 VA** (coupures fréquentes) | + parasurtenseur |

La mémoire de la carte graphique fixe la taille des modèles utilisables : 12 Go suffisent
pour un modèle de 7 à 8 milliards de paramètres et pour entraîner les petits modèles de
vision (MobileNet, EfficientNet, YOLO). Sans carte graphique, le service marche mais
répond en 30 à 60 s et l'entraînement devient très long.

## 1 bis. Sur un VPS (choix retenu pour commencer, réponse 54)

Pas de machine chez LY pour l'instant : le service IA tourne sur un **VPS sans carte
graphique**.

| | Conseillé |
| --- | --- |
| Processeur | 8 vCPU (4 au strict minimum) |
| Mémoire | **16 Go** (32 Go pour un modèle de 7 milliards de paramètres) |
| Disque | SSD 100 Go (modèles, système) |
| Système | Ubuntu 24.04 LTS, Docker |

- Modèle : `qwen2.5:3b-instruct` (réglage par défaut). Une réponse prend de l'ordre d'une
  minute : acceptable, tout passe par la file d'attente.
- Lancer **sans** `docker-compose.gpu.yml` : `docker compose up -d --build`.
- Même VPS que la plateforme : `IA_ECOUTE=127.0.0.1` et `IA_URL=http://127.0.0.1:8100`.
  VPS séparé : relier les deux par WireGuard (partie 3).
- **Données** : les photos et observations sont envoyées au VPS pour le diagnostic. Prendre
  un hébergeur dont on connaît le pays des serveurs, chiffrer le disque si possible, et
  s'assurer que le consentement des producteurs (question 21) couvre ce traitement.
- **Entraînement** : pas sur ce VPS (sans carte graphique, beaucoup trop lent). Le moment
  venu, louer une machine avec carte graphique à l'heure, y copier l'export
  (`ia:exporter-jeu`), entraîner, rapatrier le modèle ONNX dans `ia/modeles/`, puis rendre
  la machine.

## 2. Système

1. Ubuntu Server 24.04 LTS, mises à jour automatiques de sécurité activées.
2. Pilote NVIDIA (`sudo ubuntu-drivers install`), redémarrer, vérifier `nvidia-smi`.
3. Docker Engine + le plugin compose, puis `nvidia-container-toolkit` (pour que les
   conteneurs voient la carte). Vérifier :
   `docker run --rm --gpus all nvidia/cuda:12.4.1-base-ubuntu22.04 nvidia-smi`.
4. Pare-feu : tout fermé depuis Internet (`ufw default deny incoming`), SSH seulement
   depuis le VPN.

## 3. VPN entre la plateforme et le serveur IA

WireGuard, deux pairs : le serveur de la plateforme et le serveur IA (adresse VPN
`10.8.0.2` dans les exemples). Le service IA n'écoute **que** sur cette adresse
(`IA_ECOUTE` dans `ia/.env`). Tester depuis le serveur de la plateforme :
`curl http://10.8.0.2:8100/sante`.

## 4. Service IA

```bash
git clone <dépôt> ly-agricole && cd ly-agricole/ia
cp .env.example .env
python3 -c "import secrets; print(secrets.token_urlsafe(32))"   # → IA_JETON dans .env
docker compose up -d --build        # les tests du contrôle tournent pendant la construction
docker compose exec ollama ollama pull qwen2.5:7b-instruct       # plusieurs Go
curl http://10.8.0.2:8100/sante
```

Le modèle est un choix de réglage (`OLLAMA_MODELE`) : on en essaie plusieurs sur des
observations réelles, et on garde celui dont le plus de brouillons passent le contrôle et
la relecture. Aucun modèle n'est « fiable » d'office : le contrôle après génération reste
actif quel que soit le modèle.

## 5. Brancher la plateforme

Dans le `.env` de Laravel :

```
IA_URL=http://10.8.0.2:8100
IA_JETON=<le même jeton>
```

puis `php artisan config:clear`. Les demandes partent par la file d'attente
(`php artisan queue:work`) : jamais pendant qu'un utilisateur attend une page.

## 6. Sauvegardes et entraînement

- Les photos et les diagnostics sont dans la base de la plateforme (sauvegardée chaque
  nuit, semaine 12). Le serveur IA ne garde que les modèles : sauvegarder `ia/modeles/`
  sur le 2ᵉ disque après chaque nouveau modèle.
- **En attendant un agronome** (réponse 55) : les photos s'annotent **provisoirement**
  pendant les sessions Claude (`php artisan ia:annoter` liste les photos, puis
  `php artisan ia:annoter <n°> <classe> --note="…"`). Ce n'est pas une validation : le
  diagnostic reste « incertain », rien n'est conseillé, et ces annotations n'entrent dans
  l'export que sur demande (`--avec-provisoires`), jamais dans le jeu de test.
- **Entraînement** : à partir de photos **confirmées ou corrigées par un agronome**
  (`php artisan ia:exporter-jeu`). Un nouveau modèle est comparé à l'ancien sur
  un **jeu de test fixe**, maladie par maladie (matrice de confusion), avant de remplacer
  l'ancien. Tant qu'aucun agronome n'a confirmé de photos, il n'y a rien à entraîner.

## 7. Ce qui reste interdit, quel que soit le matériel

- Le modèle n'écrit jamais un nom de produit ni une dose : il cite les fiches validées du
  référentiel, et le code rejette toute réponse qui s'en écarte.
- Tout diagnostic reste « proposé » ou « incertain » tant qu'un agronome ne l'a pas
  confirmé ; rien n'est envoyé au producteur comme une certitude.
- **Sans agronome, aucun conseil ne sort de la plateforme** : les brouillons restent
  internes.
