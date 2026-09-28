---
name: ly-agricole-terrain-hors-ligne
description: Règles de l'appli terrain de LY AGRICOLE (SvelteKit + Dexie + Capacitor) et de sa synchronisation avec Laravel — saisie hors ligne des producteurs, parcelles GPS, achats, photos et dépenses, file d'envoi, point /api/sync idempotent, rejets et conflits. À charger dès qu'on touche au dossier terrain/, aux routes API, à Sanctum, à l'endpoint de synchronisation, ou qu'on se demande « et si l'agent n'a pas de réseau ? ».
---

# Appli terrain et synchronisation

Les agents achètent et pèsent dans des villages **sans réseau**. L'appli doit tout
enregistrer en local et envoyer plus tard, sans jamais créer de doublon ni perdre une
saisie. C'est pour cela qu'elle n'est pas en Livewire (décision D2).

## Principes

1. **Le téléphone crée l'identifiant** : UUID v7 généré à la saisie. Le serveur
   l'accepte tel quel ; c'est la clé d'idempotence.
2. **Le téléphone crée, il ne modifie pas l'argent.** Hors ligne on peut créer un
   achat, un producteur, une parcelle, une visite, une photo, une dépense terrain.
   Corriger un achat déjà envoyé = une demande de correction traitée au bureau
   (contre-passation), jamais une modification locale réenvoyée.
3. **File d'envoi explicite** : chaque saisie est une opération en attente, visible à
   l'écran « À envoyer » avec son état (`en_attente`, `envoye`, `rejete` + motif).
   L'agent sait toujours ce qui n'est pas encore au bureau.
4. **Le serveur reste juge.** Il revalide tout (plafond, stock, caisse, droits).
   Une opération refusée revient avec un motif lisible ; elle n'est jamais perdue en
   silence.

## Contrat de `/api/sync`

- `POST /api/sync` avec `{ appareil_id, operations: [{ uuid, type, cree_at, donnees }] }`.
- Réponse : pour **chaque** opération `{ uuid, statut: accepte | deja_recu | rejete, motif? }`.
- `deja_recu` n'est pas une erreur : c'est le cas normal d'un envoi répété après une
  coupure au milieu de la réponse.
- Traitement opération par opération, chacune dans sa transaction : une opération
  refusée ne bloque pas les autres, sauf dépendance (un achat qui référence un
  producteur créé dans le même envoi : traiter dans l'ordre reçu).
- Chaque appel est tracé dans `synchronisations`.
- Tests obligatoires : même envoi deux fois ⇒ aucun doublon ; envoi coupé en deux
  moitiés ⇒ même résultat qu'un envoi complet ; opération invalide ⇒ les autres passent.

## Photos

Envoyées **à part** des opérations (fichiers lourds, réseau faible) : l'opération
référence l'UUID de la photo ; la photo peut arriver après. Compresser sur le
téléphone avant l'envoi. Une photo de pesée garde GPS et heure.

## Téléchargement vers le téléphone

Référentiels (villages, produits, campagne ouverte, prix, points de collecte) et
producteurs de la zone de l'agent. Chaque téléchargement porte un horodatage ; le
suivant ne demande que ce qui a changé depuis.

## Pièges

- `navigator.onLine === true` ne veut pas dire « internet fonctionne » ; seul `false`
  est fiable. Tenter l'envoi et traiter l'échec, c'est la seule vérité.
- L'heure du téléphone peut être fausse : garder `cree_at` (téléphone) **et**
  `recu_at` (serveur) ; les calculs de date se font sur une heure de référence claire.
- Tester en **mode avion sur un vrai téléphone**, pas seulement dans le navigateur de
  bureau : la mise en veille de l'appli et le redémarrage du téléphone doivent laisser
  la file intacte.
