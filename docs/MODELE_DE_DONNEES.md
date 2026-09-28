# Modèle de données — phase 1

Projet de schéma à valider pendant la semaine 1. Conventions : `docs/DECISIONS.md`
(D3 UUID, D4 FCFA entiers et grammes, D5 registres immuables, D8 noms français).

Légende : 📱 = créable hors ligne sur l'appli terrain (clé UUID v7 fournie par le
téléphone) · 🔒 = registre immuable (jamais `UPDATE` ni `DELETE`, correction par
contre-passation).

```mermaid
flowchart TD
  Campagne --> Pret
  Producteur --> Parcelle
  Producteur --> Pret
  Pret --> Decaissement
  Pret --> Remboursement
  Achat --> Remboursement
  Achat --> Lot
  Lot --> MouvementStock
  Depense --> MouvementTresorerie
  Decaissement --> MouvementTresorerie
```

## Référentiels

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `users` | nom, telephone, email, role, actif | rôles : `direction`, `agent`, `agronome`, `comptable`, `investisseur`, `admin` |
| `zones` | nom | région / département de collecte |
| `villages` | zone_id, nom, lat, lng | |
| `produits` | code (`anacarde`, `karite`, `tomate`…), nom, unite | |
| `campagnes` | code (`2026-2027`), produit_id, debut, fin, statut, prix_officiel_kg_fcfa | statut : `preparation`, `ouverte`, `cloturee` |
| `magasins` | nom, village_id, capacite_kg | |
| `points_collecte` | nom, village_id | |

## Producteurs et parcelles

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `producteurs` 📱 | uuid, code (carte QR), nom, prenoms, sexe, annee_naissance, telephone, numero_mobile_money, operateur_mm, piece_type, piece_numero, photo, village_id, groupe_id, consentement_at, consentement_par, cree_par | doublons détectés sur téléphone + pièce |
| `groupes_producteurs` | nom, village_id, responsable_id | caution solidaire en phase 2 |
| `parcelles` 📱 | uuid, producteur_id, nom, contour_geojson, surface_m2 (calculée), culture, annee_plantation, nb_arbres, sol, acces_eau | surface calculée à partir du contour, jamais saisie |

## Prêts

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `prets` | uuid, reference, producteur_id, campagne_id, montant_fcfa, forme (`especes`, `mobile_money`, `intrants`, `mixte`), prix_reference_kg_fcfa, grammes_attendus, echeance, statut, cree_par, valide_par, valide_at, motif_cloture | statut : `demande`, `valide`, `refuse`, `decaisse`, `en_cours`, `solde`, `reporte`, `perte` ; `valide_par ≠ cree_par` |
| `pret_parcelle` | pret_id, parcelle_id | hectares financés = somme des surfaces |
| `decaissements` 🔒 | pret_id, montant_fcfa, mode, reference_paiement, compte_tresorerie_id, date, justificatif, cree_par | produit un mouvement de trésorerie sortant |
| `remboursements` 🔒 | pret_id, type (`especes`, `nature`), montant_fcfa, achat_id (si nature), mouvement_tresorerie_id (si espèces), date, annule_id | restant dû = montant − somme des remboursements |

## Intrants

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `intrants` | nom, unite, prix_unitaire_fcfa | engrais, sacs, bâches |
| `mouvements_intrants` 🔒 | intrant_id, magasin_id, type (`entree`, `distribution`, `perte`, `ajustement`), quantite, pret_id, date, annule_id | une distribution à crédit alimente le prêt en nature |

## Achats et stock

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `achats` 📱 | uuid, reference, campagne_id, produit_id, fournisseur_type (`producteur`, `pisteur`, `cooperative`), producteur_id, fournisseur_nom, point_collecte_id, agent_id, date_heure, lat, lng, poids_brut_g, tare_g, poids_net_g, humidite_pour_mille, kor_centieme_lbs, grainage_noix_kg, prix_kg_fcfa, montant_fcfa, mode_paiement, pret_id, lot_id, photo_pesee, statut | un achat lié à un prêt génère un remboursement en nature |
| `pisteurs` | nom, telephone, taux_commission | |
| `lots` | code, produit_id, campagne_id, magasin_id, statut, cree_at | statut : `ouvert`, `en_stock`, `vendu`, `transforme` |
| `mouvements_stock` 🔒 | lot_id, magasin_id, type (`entree_achat`, `transfert_sortie`, `transfert_entree`, `perte`, `ajustement_inventaire`, `sortie_vente`), grammes (signé), date, motif, achat_id, annule_id, cree_par | stock d'un lot = somme des grammes |
| `inventaires` | magasin_id, date, compte_par, valide_par | lignes : lot_id, grammes_comptes, ecart |

## Trésorerie et dépenses

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `comptes_tresorerie` | nom, type (`caisse`, `banque`, `wave`, `orange_money`, `mtn_momo`), titulaire_id (caisse d'agent), actif | |
| `mouvements_tresorerie` 🔒 | compte_id, sens (`entree`, `sortie`), montant_fcfa, date, libelle, source_type, source_id, reference_externe, annule_id, cree_par | solde = somme ; virement interne = deux mouvements liés |
| `categories_depense` | nom, code_syscohada, exclue_fonds_campagne | art. 10.3 du contrat |
| `depenses` 📱 | uuid, categorie_id, montant_fcfa, date, beneficiaire, justificatif, campagne_id, lot_id, pret_id, parcelle_id, statut, cree_par, valide_par, valide_at | au-dessus du seuil : validation obligatoire par une autre personne |
| `avances_agents` | agent_id, montant_fcfa, date, justifie_fcfa (calculé) | reste à justifier = avance − achats − dépenses justifiées |

## Transverse

| Table | Colonnes principales | Remarques |
| --- | --- | --- |
| `confirmations_sms` | producteur_id, objet_type, objet_id, message, envoye_at, statut, reponse | preuve envoyée au producteur |
| `journal_activite` 🔒 | user_id, action, objet_type, objet_id, avant, apres, ip, appareil, at | qui a fait quoi |
| `synchronisations` | appareil_id, user_id, recu_at, nb_operations, nb_rejetees, erreurs | trace des envois de l'appli terrain |
| `parametres` | cle, valeur | seuils de validation, plafonds, prix |

## Invariants à tester dès la semaine où la table naît

1. Restant dû d'un prêt = `montant_fcfa − Σ remboursements` et n'est jamais négatif
   (un trop-perçu devient un crédit producteur explicite, pas un nombre négatif).
2. Stock d'un lot = `Σ mouvements_stock.grammes` ≥ 0.
3. Solde d'un compte = `Σ entrées − Σ sorties` ; une caisse ne passe jamais en négatif.
4. Un achat enregistré deux fois avec le même UUID = un seul achat.
5. `valide_par ≠ cree_par` sur tout ce qui se valide.
6. Aucun `UPDATE`/`DELETE` possible sur une table 🔒 (le modèle lève une exception).
