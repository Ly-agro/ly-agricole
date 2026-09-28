# Plan d'implémentation

Début : **samedi 26 septembre 2026**. Échéance de la phase 1 : **gel le vendredi
18 décembre 2026**, début de campagne le **lundi 21 décembre 2026**. Cela fait
12 semaines, sans marge : si une semaine glisse, on retire du périmètre (liste
« coupable si retard » en bas), on ne décale pas la date.

Chaque semaine commence un samedi. Une semaine est **finie** quand :

- ses tests passent (`php artisan test`) et le **nombre** de tests a augmenté ;
- chaque invariant touché (`docs/MODELE_DE_DONNEES.md`, bas de page) a son test ;
- le parcours a été **exécuté pour de vrai** dans le navigateur ou sur le téléphone,
  pas seulement testé ;
- une entrée est ajoutée à `docs/RAPPORTS_DE_TRAVAIL.md`.

---

## Avant samedi (préalables)

- [ ] Le responsable projet a relu le cahier des charges et répondu au moins aux
      questions marquées **bloquantes** dans `docs/QUESTIONS_OUVERTES.md`.
- [ ] MySQL démarré sur le poste (XAMPP) et une base `ly_agricole` créée.
- [ ] Node 20+ et Composer disponibles (`node -v`, `composer -V`).
- [ ] Un téléphone Android de test avec le mode développeur activé (semaine 8).

## Phase 1 — le socle de campagne

| Sem. | Début | Contenu | Livrable vérifiable |
| --- | --- | --- | --- |
| 1 | sam. 26 sept. | Installation Laravel 13, Livewire 3, Tailwind 4, PHPUnit, Pint, Larastan. Authentification, rôles et politiques. Référentiels : zones, villages, produits, campagnes, magasins, points de collecte, paramètres. Journal d'activité. | Un utilisateur par rôle se connecte ; chacun ne voit que ses écrans ; toute création apparaît au journal. |
| 2 | sam. 3 oct. | Producteurs (fiche, photo, pièce, Mobile Money, consentement, détection de doublons), carte producteur PDF à QR code, groupes. Parcelles : import d'un contour GeoJSON, surface calculée. | Créer 10 producteurs réels de test, imprimer une carte, la scanner au téléphone. |
| 3 | sam. 10 oct. | Trésorerie : comptes, mouvements immuables, contre-passation, virements internes, soldes. Dépenses avec justificatif, catégories, seuil de validation, séparation des tâches. Avances aux agents. | Une dépense au-dessus du seuil est refusée à son propre auteur ; le solde de caisse = somme des mouvements. |
| 4 | sam. 17 oct. | Prêts : demande → validation → décaissement (espèces / Mobile Money manuel), plafonds, rattachement aux parcelles, kilos attendus. Tableau du portefeuille. | Le cas « 7 prêts de 3 M FCFA » saisi de bout en bout ; la caisse baisse de 21 M. |
| 5 | sam. 24 oct. | Intrants : stock, distribution à crédit rattachée au prêt (prêt en nature), reçus PDF. | Un prêt mixte espèces + engrais, restant dû correct. |
| 6 | sam. 31 oct. | Achats bord-champ (qualité : humidité, KOR, grainage), lots, mouvements de stock, transferts, inventaire. Achat lié à un prêt ⇒ remboursement en nature. Pisteurs et commissions. | Un producteur sous prêt livre 500 kg : son restant dû baisse, le lot monte de 500 kg, la caisse de l'agent baisse. |
| 7 | sam. 7 nov. | API Sanctum pour l'appli terrain ; point `/api/sync` idempotent (UUID) ; confirmation SMS au producteur (pilote `journal`), bons d'achat PDF. | Le même lot d'opérations envoyé deux fois ne crée rien en double (test). |
| 8 | sam. 14 nov. | Appli terrain (SvelteKit + Dexie + Capacitor) : connexion, téléchargement des référentiels, recherche producteur par QR, achat **hors ligne**, file d'envoi. | En mode avion : 5 achats saisis, puis envoyés au retour du réseau, visibles au bureau. |
| 9 | sam. 21 nov. | Appli terrain : relevé GPS de parcelle, photos (pesée, reçu), dépense terrain, écran « à envoyer », gestion des rejets. | Une parcelle relevée en marchant, surface affichée au bureau. |
| 10 | sam. 28 nov. | Tableaux de bord : portefeuille de prêts, stock par lot et magasin, caisses, écarts de poids, alertes de base. Exports PDF / Excel. | La direction répond sans aide à « combien reste dû, combien en stock, combien en caisse ». |
| 11 | sam. 5 déc. | **Pilote terrain** avec 1 ou 2 agents sur un vrai point de collecte. Corrections. Choix et branchement du vrai fournisseur SMS si décidé. | Liste des problèmes vus sur le terrain, chacun corrigé ou reporté par écrit. |
| 12 | sam. 12 déc. | Mise en production (serveur, sauvegardes quotidiennes testées par une restauration, HTTPS), comptes réels, formation des agents et de la comptable. **Gel vendredi 18 déc.** | Une restauration de sauvegarde réussie ; chaque agent a fait un achat de test sur son propre téléphone. |

### Coupable si retard (dans cet ordre)

1. Carte producteur PDF (garder le QR seul, imprimé plus tard).
2. Relevé GPS en marchant (garder la saisie manuelle de la surface, marquée « déclarée »).
3. Exports Excel (garder les PDF).
4. Intrants (les saisir comme dépense rattachée au prêt).

**Jamais coupable** : registres immuables, séparation des tâches, idempotence de la
synchronisation, sauvegardes. Ce sont eux qui rendent les chiffres défendables.

## Phase 2 — pendant la campagne (janv. → mars 2027)

Reventes et encaissements, marge par lot ; visites et photos ; comparaison des
rendements ; portail investisseurs et rapports PDF du contrat (art. 18) ; budget ;
note de fiabilité ; groupes et caution solidaire ; balance Bluetooth ; véhicules ;
ventes en devises ; indicateurs d'impact ; site vitrine ; API Mobile Money.

## Phase 3 — (avril → juin 2027)

Comptabilité SYSCOHADA, rapprochements, paie des journaliers ; référentiel des
traitements ; service IA (FastAPI + Ollama) en mode « assistant » avec confirmation de
l'agronome ; WhatsApp. **Les photos s'accumulent dès la phase 2** : chaque visite
alimente le futur modèle.

## Phase 4 — campagne 2027-2028

Modèle de vision sur le téléphone hors ligne ; qualité des noix par photo ;
transformation ; images satellite.
