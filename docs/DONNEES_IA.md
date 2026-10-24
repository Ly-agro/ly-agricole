# Données pour le modèle de vision (diagnostic des photos)

État au 30/09/2026 : pas d'agronome avant environ 3 mois (question 53). LY rassemble
elle-même photos et données ; tout reste **provisoire** et interne jusqu'à ce que
l'agronome valide, **avant toute mise à disposition réelle** aux producteurs.

## 1. Jeux publics vérifiés (licence lue sur la page officielle)

| Jeu | Contenu utile | Licence | Verdict |
| --- | --- | --- | --- |
| **CCMT** — Cashew, Cassava, Maize, Tomato (Ghana, 2023) | **Anacarde** : anthracnose, gommose, mineuse des feuilles, rouille rouge, sain. **Tomate** : sain, brûlure foliaire, enroulement (leaf curl), septoriose, verticilliose. 24 881 photos brutes (6 549 anacarde, 5 435 tomate), prises au champ, étiquettes validées par des virologues | **CC BY 4.0** : usage libre, y compris commercial, **à condition de citer les auteurs** | **Point de départ retenu** : même région, photos de terrain |
| **PlantVillage** (version Mendeley) | Tomate : 10 classes (sain, taches bactériennes, alternariose, mildiou, cladosporiose, septoriose, acariens, taches cibles, mosaïque, TYLCV) | CC0 sur Mendeley (une autre source indique CC BY 3.0 : citer par prudence) | **Complément seulement** : feuilles détachées sur fond uni, au labo ; un modèle entraîné dessus se trompe au champ (biais connu) |
| Karité | — | — | **Aucun jeu public trouvé** : photos de LY uniquement |

Sources : CCMT <https://data.mendeley.com/datasets/bwh3zbpkpv/1> (DOI 10.17632/bwh3zbpkpv.1) et
l'article <https://www.sciencedirect.com/science/article/pii/S2352340923004250> ;
PlantVillage <https://data.mendeley.com/datasets/tywbtsjrjv/1>, biais :
<https://arxiv.org/pdf/2206.04374>.

**Attribution obligatoire (CC BY 4.0)** — à reprendre dans toute publication, rapport ou
écran « à propos » du modèle :

> Modèle entraîné en partie sur le jeu CCMT (Mensah Kwabena P. et al., « Dataset for Crop
> Pest and Disease Detection », Mendeley Data, v1, 2023, DOI 10.17632/bwh3zbpkpv.1),
> licence CC BY 4.0.

Ce que le jeu CCMT ne couvre pas : les défauts des **noix** d'anacarde (qualité à
l'achat), le karité, et les conditions exactes de nos zones. D'où les photos de LY.

## 2. Correspondance des classes

Une classe = une culture + un nom court, le même partout (dossiers d'entraînement,
annotations provisoires, validation de l'agronome) :

| Culture | Classe LY | CCMT | PlantVillage |
| --- | --- | --- | --- |
| anacarde | `anthracnose` | Cashew anthracnose | — |
| anacarde | `gommose` | Cashew gumosis | — |
| anacarde | `mineuse` | Cashew leaf miner | — |
| anacarde | `rouille-rouge` | Cashew red rust | — |
| anacarde | `sain` | Cashew healthy | — |
| tomate | `sain` | Tomato healthy | Tomato_healthy |
| tomate | `septoriose` | Tomato septoria leaf spot | Tomato_septoria_leaf_spot |
| tomate | `enroulement-feuilles` | Tomato leaf curl | Tomato_yellow_leaf_curl_virus |
| tomate | `brulure-feuilles` | Tomato leaf blight | (early / late blight : à trancher par l'agronome) |
| tomate | `verticilliose` | Tomato verticillium wilt | — |

Les noms exacts des dossiers du jeu téléchargé sont vérifiés par le script
d'entraînement : une classe inconnue arrête le script au lieu d'être devinée.

## 3. Photos de LY (visites de parcelle)

Chaque photo prise pendant une visite est déjà enregistrée avec la culture, la date, la
parcelle et la position. Pour qu'elle serve, l'agent suit ce protocole :

1. **Une photo = un sujet** : une feuille, un fruit, une noix ou un tronc, qui remplit le
   cadre. Plus une photo d'ensemble de l'arbre ou du plant.
2. **Lumière du jour, sans contre-jour**, pas de flash. Feuille tenue à la main, sur
   fond naturel (pas de feuille blanche : c'est le biais de PlantVillage).
3. **Aussi des feuilles saines** : sans elles, le modèle voit des maladies partout.
4. Dans « Observations », décrire ce qu'on voit (« taches brunes en bordure, 30 % des
   arbres »), sans nom de producteur ni téléphone.
5. Le consentement du producteur couvre-t-il l'entraînement d'un modèle ? Question 21 et
   55 : à faire préciser avant d'utiliser ses photos pour entraîner.

Ensuite : « Demander un avis IA » sur la visite, puis annotation provisoire en session
Claude (`php artisan ia:annoter`), puis validation par l'agronome à son arrivée.

## 4. Ordre prévu

1. Maintenant : premier modèle **d'essai** sur CCMT seul (anacarde, puis tomate), pour
   rôder la chaîne ; jamais présenté comme fiable.
2. Pendant la campagne : photos de visites, annotées provisoirement.
3. À l'arrivée de l'agronome : il valide un échantillon, corrige, et fixe le jeu de test
   LY. Le modèle n'est proposé aux agents qu'après ses résultats **par maladie** sur ce jeu
   de test, et reste « à confirmer » pour chaque diagnostic.
