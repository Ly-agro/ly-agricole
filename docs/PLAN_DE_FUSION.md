# Plan de fusion des branches (préparé le 2026-09-29, rien n'est fusionné)

Analyse **en lecture seule** (`git merge-tree`, comparaison des fichiers, des tables, des
droits et des routes). Aucune branche, aucun arbre de travail, aucune base n'a été modifié.
À refaire avant de fusionner : les branches avancent.

## 1. Les lignes de travail

| Branche | Contenu | Base | Commits propres |
| --- | --- | --- | --- |
| `main` (local) | phase 1, jusqu'à la semaine 5 | — | en retard de **7 commits** sur `origin/main` (PR n° 1 : semaine 5) |
| `semaine-12` | semaines 10 à 12 : rapports de gestion (PDF / Excel), sauvegardes vérifiées | `semaine-9` | 3 |
| `phase-2-reventes` (A) | reventes, encaissements, apports, rendements, vitrine, partage du résultat, point d'étape, fiabilité | `semaine-9` | 9 (dont 7 depuis `ad360cd`) |
| `phase-2-visites` puis `phase-2-notifications` (B) | budget, visites de parcelle ; notifications push et tickets 58 mm en cours | `ad360cd` (A) | 2 (à ce jour) |

`semaine-1` à `semaine-9` forment une seule ligne ; `semaine-9` est l'ancêtre commun.

## 2. Ordre conseillé

1. **`main` ← `origin/main`** (avance rapide, 7 commits déjà fusionnés par PR).
2. **`semaine-12` dans `main`** : c'est la base du pilote (phase 1 complète, sauvegardes).
3. **`phase-2-reventes` (A)** : la plus grosse, sans migration nouvelle depuis `ad360cd`.
4. **`phase-2-notifications` (B)** en dernier : elle continue d'avancer, on la fusionne quand
   elle est stable. Elle apporte les tables `lignes_budget`, `visites`, `visite_photo` (et à venir).

Le dépôt est fusionné par **pull requests** (PR n° 1). Le plus simple : une PR par branche, dans
cet ordre, chacune avec la suite de tests au vert.

## 3. Conflits attendus (calculés)

Chaque paire ne rentre en conflit que sur 2 à 3 fichiers partagés, tous en **ajout des deux côtés** :

| Fusion | Fichiers en conflit |
| --- | --- |
| A + `semaine-12` | `routes/web.php`, `docs/QUESTIONS_OUVERTES.md`, `docs/RAPPORTS_DE_TRAVAIL.md` |
| A + B | `layouts/app.blade.php` (menu), `docs/QUESTIONS_OUVERTES.md`, `docs/RAPPORTS_DE_TRAVAIL.md` |
| `semaine-12` + B | `docs/QUESTIONS_OUVERTES.md`, `docs/RAPPORTS_DE_TRAVAIL.md` (+ `CLAUDE.md` fusionné sans conflit) |

Fichiers touchés par plusieurs branches mais fusionnés seuls par Git (à relire quand même) :
`AppServiceProvider.php`, `nav-lien.blade.php`, `MODELE_DE_DONNEES.md`.

**Règle de résolution :** garder les deux côtés ; les rapports de session par date décroissante.

**Aucune collision « silencieuse »** : tables créées (B seule : `lignes_budget`, `visites`,
`visite_photo`), droits (`voir-rapports` contre `voir-rapport-campagne`, `voir-budget`, `voir-visites`…),
noms de routes (`rapports` contre `rapport-campagne`, `budget`, `visites`…) : tous distincts.

## 4. Numéros de questions ouvertes : à corriger (seul vrai piège)

Trois branches utilisent **28, 29 et 30 pour des questions différentes** :

| N° | `semaine-12` | A | B |
| --- | --- | --- | --- |
| 28 | Exports « Excel » | Plusieurs activités | — |
| 29 | Seuil d'alerte d'écart de poids | — | Budget de campagne |
| 30 | Qui voit les rapports ? | — | Visites de parcelle |

Le fichier de réponses du responsable projet numérote **28 = activités, 29 = budget, 30 = visites,
31 = rendement, 32 = résultat, 33 = CSV/XLSX, 34 = écart de poids**. Recommandation : adopter *cette*
numérotation, donc à la fusion :

- `semaine-12` : 28 (exports) → **33**, 29 (seuil d'écart) → **34**, 30 (qui voit les rapports) → **36** ;
- A : ses lignes 33 et 34 sont des **doublons** des précédentes : fusionner les réponses, supprimer les
  doublons ; 35 (fiabilité) et 31, 32 restent ;
- B : 29 et 30 restent.

Mettre à jour les références dans le code et les docs (`grep -rn "question ouverte n° 2[89]\|n° 30"`,
`.claude/skills/ly-agricole-metier/SKILL.md`, commentaires de `RendementsTest`, `PartageResultat`…).

## 5. Après chaque fusion

1. `php artisan config:clear` puis `php artisan test` : **le nombre de tests doit être la somme des deux
   côtés** (règle du projet : comparer le nombre, pas seulement le vert).
2. `vendor/bin/phpstan analyse --memory-limit=1G` et `vendor/bin/pint`.
3. `php artisan migrate` sur une **base de contrôle** (jamais sur la base de dev d'une session), puis un
   parcours réel : connexion, un écran de chaque bloc (`/`, `/resultat`, `/rapport-campagne`, `/fiabilite`,
   `/rendements`, `/rapports`, `/budget`, `/visites`).
4. `npm run build` (back-office) et, côté terrain, `npm test`, `npm run check`, `npm run build`
   (la base Dexie passera par les versions des deux branches : voir plus bas).

**Point d'attention terrain.** Les deux lignes ont modifié `terrain/src/lib/db.ts` : B ajoute une table
`parcelles` (version 3 de la base locale). Toute autre table locale ajoutée ailleurs doit prendre la
version 4, jamais réutiliser ou modifier une version publiée.

## 6. Décisions à prendre par le responsable projet

- Lancer la fusion, et **dans quel ordre** (recommandé ci-dessus) ; A prépare, B relit ses parties.
- **Pousser** les branches vers `origin` (aujourd'hui `origin/phase-2-reventes` est en retard de 7 commits) et passer par des PR.
- Adopter la numérotation du fichier de réponses (section 4).
- Le PDF du contrat (RIB) ne doit **jamais** entrer dans le dépôt : à exclure par `.gitignore` avant tout push.

## 7. Résultat (2026-09-29)

Fusion faite sur la branche `fusion-phase-2` (depuis `origin/main`) : `semaine-12`, puis `phase-2-reventes`, puis `phase-2-visites` (`078bc8b`). Conflits résolus comme prévu, numérotation des questions alignée sur le fichier de réponses. Détail et vérifications : `docs/RAPPORTS_DE_TRAVAIL.md`. Reste à fusionner plus tard : `phase-2-notifications` (B).
