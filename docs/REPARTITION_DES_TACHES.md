# Répartition des tâches entre sessions

Créé le 2026-09-29 à la demande du responsable projet, après que deux sessions ont
construit **le même bloc** (visites de parcelle) en même temps. Le fichier de référence
est celui de l'arbre `C:\xampp\htdocs\ly-agricole\docs\` ; chaque session le lit avant de
commencer un bloc et le met à jour quand elle en prend ou en finit un.

## Pourquoi c'est arrivé

1. Aucune des deux n'avait dit qu'elle commençait ce bloc avant de l'écrire.
2. Les deux partageaient **la même base MySQL** : la migration de l'une a pris le nom de
   table que l'autre voulait créer.

## Sessions

| | Session A | Session B |
| --- | --- | --- |
| Nom | `ly-agricole-fb` (celle qui écrit ce fichier) | `ly-agricole-45` |
| Arbre de travail | `C:\xampp\htdocs\ly-agricole` | `C:\xampp\htdocs\ly-agricole-budget` |
| Branche | `phase-2-reventes` | `phase-2-visites` (au-dessus de `phase-2-budget`) |
| Thème | **Argent, résultat et rapports** | **Terrain, producteurs et visites** |

Les deux branches partent du commit `ad360cd`.

## Blocs et propriétaires

Un bloc a **un seul** propriétaire. Il est seul à écrire dans ses fichiers.

| Bloc (plan de phase 2 et questionnaire) | Propriétaire | État |
| --- | --- | --- |
| Reventes, encaissements, marge par lot | A | fini |
| Apports de campagne, portail investisseur (base) | A | fini |
| Rendements : classement, carte, évolution | A | fini |
| Vitrine publique et logo | A | fini |
| Partage du résultat (contrat art. 10 à 14), écran `/resultat` | A | fini (provisoire) |
| Budget de campagne | B | fini |
| Visites de parcelle et photos | B | en cours |
| **Vue investisseur du résultat** | A | attend la question 32 |
| **Rapport PDF du contrat (art. 18)** : point d'étape (18.1) | A | fini, non commité |
| Rapport final (art. 18.2) | A | attend la question 32 |
| ~~Exports CSV / XLSX / PDF (question 33)~~ | — | **déjà fait** : la branche `semaine-10` (phase 1, pas encore fusionnée) a les rapports de gestion avec exports PDF et Excel (`Rapports`, `RapportController`, `/rapports`). À vérifier à la fusion ; rien à coder ici |
| **Note de fiabilité** (producteur, d'après prêts et livraisons) | A | fini, commité |
| **Ventes en devises** | A | à faire |
| **API Mobile Money** | A | à faire |
| **Comparaison des pratiques** des 20 % meilleurs et moins bons | B | après les visites ; **nouveau service** `ComparaisonPratiques`, on ne modifie pas `Rendements` |
| **Groupes et caution solidaire** | A (repris de B, 2026-09-29, à la demande du responsable projet) | en cours |
| **Balance Bluetooth** | A (repris de B) | à faire, après les groupes ; réutilise `terrain/src/lib/imprimante.ts` (BLE) ; prochaine table locale = Dexie version 4 |
| **Alerte d'écart de poids** (question 34) | B | à faire |
| **Jetons et appareils** (révocation, liste, dernière synchro, question 25) | A (repris de B) | à faire |
| **Langue par producteur** (question 12) et **commission des pisteurs** (question 6) | A (repris de B) | à faire |
| **Notifications push** : web push bureau (validations en attente), push agents (FCM + APK), alertes direction. Fichiers propres à B (`Notifications/*`, observateurs, tables `abonnements_push` et `notifications_envoyees`) ; expose `Notifications::envoyer(...)` que A peut appeler. **La source d'une alerte reste à son propriétaire** (seuil de caisse, prêt en retard : A) | B | réclamé 2026-09-29 |
| **Impression de reçus 58 mm** (ESC/POS Bluetooth depuis l'appli terrain, page 58 mm au bureau) ; réutilise `BonAchat` / `RecuRemise` en lecture seule, fichiers nouveaux pour le format 58 mm | B | réclamé 2026-09-29 |
| Véhicules, indicateurs d'impact, module Conseil du Coton et de l'Anacarde (question 16) | **personne** | à réclamer (voir règle 1) |

## Règles

1. **Réclamer avant de coder.** Avant la première ligne d'un bloc : (a) lire ce tableau ;
   (b) s'il est libre, l'annoncer à l'autre session **par message** ; ce fichier étant dans
   l'arbre de A, **A inscrit la ligne** au registre (pour un bloc de B comme pour un bloc
   de A) dès qu'elle reçoit la réclamation ; (c) attendre que l'autre n'ait rien réclamé
   de contradictoire. Un bloc réclamé par
   l'autre ne se commence pas, même « juste pour voir ».
2. **Son arbre, sa branche.** On n'écrit jamais dans l'arbre de l'autre session. Une
   demande d'aide passe par un message, pas par une modification.
3. **Fichiers partagés en ajout seulement** : `routes/web.php`, `AppServiceProvider`, le
   menu (`layouts/app`, `nav-lien`), `QUESTIONS_OUVERTES.md`, `RAPPORTS_DE_TRAVAIL.md`,
   `MODELE_DE_DONNEES.md`. On ajoute des lignes, on ne réécrit pas celles de l'autre. Les
   conflits de fusion se règlent en gardant les deux.
4. **Une base MySQL par session.** Recommandé : A garde `ly_agricole`, B utilise
   `ly_agricole_b` (`DB_DATABASE` dans son `.env`, puis `php artisan migrate`). Tant que
   ce n'est pas fait, **annoncer avant tout `migrate` ou `migrate:rollback`**, et
   vérifier que le nom de table n'existe pas dans l'autre arbre (`grep -r "create('nom'"`).
5. **Numéros de questions ouvertes réservés** : A prend 31 à 49 ; B prend 29, 30, puis 50 à
   69. Avant d'en créer une, lire le fichier des deux arbres.
6. **Rapport de session** : chacune écrit sa propre entrée en haut de
   `RAPPORTS_DE_TRAVAIL.md`, avec le nom de sa branche.
7. **Fin de bloc** : tests, Larastan, Pint, commit sur sa branche, puis message à l'autre
   avec la liste des fichiers partagés touchés. Aucune session ne pousse ni ne fusionne dans
   `main` sans que le responsable projet le demande.
8. **Un lourd à la fois** : le poste manque de mémoire. Ne pas lancer en même temps la suite
   complète de tests, Larastan et `npm run build` dans les deux arbres ; se prévenir avant.
9. **Les décisions du responsable projet valent pour les deux.** Quand il en donne une à une
   session, elle la transmet à l'autre par message et la note dans `QUESTIONS_OUVERTES.md`.

## Ordre de fusion suggéré (au responsable projet)

`phase-2-reventes` (A) puis `phase-2-visites` (B), ou l'inverse : les deux partent de
`ad360cd`. Les conflits attendus sont limités aux fichiers partagés de la règle 3 ; ils se
résolvent en gardant les deux côtés. Numérotation de questions et migrations n'entrent pas
en collision si la règle 5 est suivie.

## Registre des réclamations

*(Ajouter une ligne : date, session, bloc, branche. Barrer quand c'est fini.)*

| Date | Session | Bloc | Branche |
| --- | --- | --- | --- |
| 2026-09-29 | B | Visites de parcelle et photos | `phase-2-visites` |
| 2026-09-29 | B | Notifications push (bureau, agents, alertes direction) | `phase-2-visites` (à confirmer) |
| 2026-09-29 | B | Impression de reçus 58 mm | `phase-2-visites` (à confirmer) |
| 2026-09-29 | B | **Alertes** : commande quotidienne `notifications:alertes` + table `alertes_envoyees` (validations en attente > 48 h, prêts en retard, budget dépassé, campagne ouverte / prix officiel, écart de poids). Lecture seule sur les données de A ; le prêt en retard réutilise la définition de `FiabiliteProducteur` | `phase-2-notifications` |

**Base de données (règle 4)** : B passe sur `ly_agricole_b` (copie de `ly_agricole` par
`mysqldump`, lecture seule sur l'originale) ; A garde `ly_agricole`. Annoncé le 2026-09-29.
| 2026-09-29 | A | Rapport de campagne, art. 18 (point d'étape) | `phase-2-reventes` — préfixe `/rapport-campagne`, vues `rapport-campagne/`, droit `voir-rapport-campagne`, pour ne pas heurter `/rapports` de `semaine-10` |
| 2026-09-29 | A | Note de fiabilité du producteur | `phase-2-reventes` — `/fiabilite`, service `FiabiliteProducteur`, droit `voir-fiabilite` |

**Branches (2026-09-29).** `fusion-phase-2` = `semaine-12` + A + B (budget, visites) : à pousser et fusionner dans `main` **sans y ajouter de commit**. Le travail suivant de A part de là, sur `phase-2-suite`. B a `phase-2-notifications` (`b23c849`) ; elle y fusionne `fusion-phase-2` pour ne résoudre les conflits qu'une fois. Ordre des PR : `fusion-phase-2`, puis `phase-2-notifications`.
| 2026-09-29 | B | **IA / LLM** (phase 3) : service `ia/` (Python FastAPI + Ollama), `App\Services\Ia\*`, écrans et routes `/ia/*` | à définir |
| 2026-09-29 | A | Décisions des questions 32 et 35 (valorisation du stock, décision de plafond) ; vitrine animée | `phase-2-suite` (commité) |
| 2026-09-29 | A | Groupes et caution solidaire, puis balance Bluetooth, appareils et jetons, langue par producteur, commission des pisteurs (repris de B, dans cet ordre) | `phase-2-suite` (sur `phase-2-alertes`) |
