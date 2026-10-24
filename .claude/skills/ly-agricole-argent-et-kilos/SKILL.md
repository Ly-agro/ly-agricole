---
name: ly-agricole-argent-et-kilos
description: Règles de calcul et d'écriture pour tout ce qui compte de l'argent ou des poids dans LY AGRICOLE — prêts, décaissements, remboursements (espèces ou en kilos), achats, lots, mouvements de stock, trésorerie, caisses d'agents, Mobile Money, dépenses, validations. À charger AVANT d'écrire une migration, un modèle, un service ou un test qui manipule un montant, un solde, un poids ou un stock, et avant toute correction d'une donnée financière (« annule ce paiement », « le poids était faux », « corrige la caisse »).
---

# Argent et kilos

Ces chiffres finissent dans un rapport signé devant des investisseurs (contrat,
art. 18). La règle de fond : **chaque nombre affiché doit pouvoir être recalculé à
partir de mouvements qu'on ne peut pas effacer.**

## 1. Types

- Montants : `BIGINT` en **FCFA entiers** (`montant_fcfa`). Pas de centimes, pas de
  `float`, pas de `decimal` pour le FCFA.
- Poids : `BIGINT` en **grammes** (`poids_net_g`, `grammes`). Affichage en kg à la
  vue seulement.
- Prix au kilo : FCFA entier par kg. Montant d'un achat =
  `intdiv(poids_net_g * prix_kg_fcfa + 500, 1000)` — **arrondi au franc le plus proche,
  une seule fois, au moment de l'achat**, puis stocké. Ne jamais recalculer un montant
  historique avec un prix actuel.
- Devises (phase 2) : unités mineures + taux figé à l'encaissement.

## 2. Registres immuables

Tables concernées : `mouvements_tresorerie`, `mouvements_stock`, `mouvements_intrants`,
`decaissements`, `remboursements`, `journal_activite`.

- Le modèle refuse `update` et `delete` (lever une exception dans `updating` /
  `deleting`). Un test le vérifie pour chaque table.
- Corriger = **contre-passer** : nouveau mouvement de sens inverse, `annule_id` =
  l'original, motif obligatoire, auteur tracé. Puis, si besoin, le bon mouvement.
- Solde / stock / restant dû = **somme** des mouvements. Un cache (colonne ou vue) est
  permis pour la vitesse, mais il doit être recalculable par une commande et un test
  compare cache et somme.

## 3. Écrire plusieurs choses d'un coup

Un même événement touche souvent plusieurs registres. Exemple : un producteur sous prêt
livre 500 kg payés en partie en espèces.

```
DB::transaction(function () {
    achat            (poids, qualité, prix, montant)
    mouvement_stock  +500 000 g sur le lot
    remboursement    nature, valeur retenue sur le prêt
    mouvement_tresorerie  sortie de la caisse de l'agent pour la part payée en espèces
    confirmation_sms en file d'attente (après commit)
});
```

Tout ou rien : une transaction, et les envois externes (SMS, notifications) **après**
le commit (`afterCommit`), jamais au milieu.

## 4. Séparation des tâches

- Toute entité validable a `cree_par`, `valide_par`, `valide_at`.
- Refus si `valide_par === cree_par`, dans le service (pas seulement dans la vue).
- Seuils (dépense, achat, prêt) = `parametres`, jamais en dur.
- Test obligatoire : l'auteur tente de valider sa propre saisie → refus.

## 5. Invariants (un test chacun, dès que la table existe)

1. Restant dû = montant − Σ remboursements, jamais négatif (un trop-perçu devient un
   crédit producteur explicite).
2. Stock d'un lot = Σ grammes ≥ 0 ; une sortie qui rendrait le stock négatif est
   refusée.
3. Solde de caisse = Σ entrées − Σ sorties ≥ 0.
4. Même UUID envoyé deux fois = une seule ligne.
5. `valide_par ≠ cree_par`.
6. Aucune modification possible d'un registre immuable.
7. Somme des quotes-parts d'une campagne = résultat net (contrat art. 12-14), vérifié
   sur l'exemple chiffré de l'art. 14.

## 6. Pièges

- Ne jamais additionner des FCFA et des grammes dans la même colonne « valeur ».
- Un remboursement en nature se valorise au prix **fixé par la règle du prêt** (prix
  du jour ou prix convenu — question ouverte n°3) : ne pas choisir à la place du
  responsable projet.
- Un poids d'achat et le poids entré en stock peuvent différer (séchage, sacs) : ce
  sont deux nombres, l'écart est une donnée, pas une erreur à masquer.
- Les tests tournent sur sqlite : vérifier que `BIGINT` et les sommes se comportent
  pareil qu'en MySQL sur des valeurs au-delà de 2³¹ (21 M FCFA ne pose pas de problème,
  mais des grammes cumulés sur une campagne, si : 3 000 t = 3 × 10⁹ g).
