# Rapports de travail

Une entrée par session, la plus récente en haut : ce qui a été fait, ce qui a été
**vérifié en l'exécutant** (et comment), ce qui reste, ce qui a surpris.

---

## 2026-09-28 — Semaine 4 (en avance) : prêts de campagne

**Fait.**

- Tables `prets`, `validations_pret` 🔒, `pret_parcelle`, `decaissements` 🔒 ;
  nature de mouvement `decaissement_pret` ; paramètres `plafond_pret_producteur_fcfa`,
  `plafond_pret_hectare_fcfa`.
- `App\Services\Prets` : demande (producteur actif, campagne non clôturée, échéance
  future, parcelles du producteur, plafonds s'ils sont définis, art. 17.3 avec accord
  écrit) ; validation par la **direction**, jamais l'auteur, **deux validateurs
  distincts au-dessus du seuil ou seuil non défini** ; refus motivé ; décaissement par
  tranches (≤ reste, même forme que le prêt, bon type de compte, reçu signé en espèces,
  référence en Mobile Money), sous verrou ; `decaisse` quand tout est versé ; une
  contre-passation du versement rouvre le reste (`valide`).
- Écrans : portefeuille (demandes, accordé, décaissé, reste, kilos attendus) et liste ;
  demande (parcelles cochées, partie liée) ; fiche (validations, refus, versements,
  pièces sur disque privé).
- Droits : `voir-prets`, `saisir-prets` (direction, comptable, agent), `valider-prets`
  (direction), `decaisser-prets` (direction, comptable).

**Décisions prudentes en attendant les questions 2, 3, 4, 5** : seulement espèces et
Mobile Money ; prix de référence = **estimation** des kilos, pas la règle de
valorisation ; **aucun intérêt** ; seuil non défini = double validation ; plafond non
défini = pas de plafond automatique (la validation reste).

**Vérifié en l'exécutant.**

- `php artisan test` : **223 tests** (194 → 223), 1 024 assertions, dont le livrable en
  test (7 × 3 M → caisse − 21 M). Larastan 0, Pint propre.
- **Livrable de la semaine 4, dans Chrome, de bout en bout** (MySQL) : agent → 5
  producteurs de plus (7) ; direction → seuil de prêt 5 000 000 (**valeur d'essai**,
  question 5) ; comptable → apport de 20 000 000 (caisse 25 000 000) ; agent → 7
  demandes de 3 000 000 à 400 FCFA/kg ; direction → 7 validations (bouton) ; comptable
  → versement sans reçu refusé (« joindre le reçu signé »), puis 7 versements avec reçu.
  Portefeuille : 7 accordés · 21 000 000, décaissé 21 000 000, reste 0, 52 500 kg
  attendus. **MySQL : caisse 4 000 000, 21 000 000 décaissés en 7 versements**, auteur
  (agent) ≠ validateur (direction) sur les 7.

**Surprise.** Un versement (LYPR-000006) n'était pas parti : clic pendant l'envoi du
reçu ; vu dans MySQL, pas à l'écran, et refait. Même comportement qu'en semaine 2
(bouton désactivé le temps d'une requête) : un utilisateur doit attendre la fin de
l'envoi du fichier.

**Reste.** Réponses aux questions 2, 3, 4, 5 ; prêts en intrants (semaine 5) ;
remboursements et statuts `en_cours` / `solde` / `perte` (semaine 6).

---

## 2026-09-28 — Semaine 3 (en avance) : trésorerie et dépenses

**Fait.**

- `comptes_tresorerie` (caisse, banque, Wave, Orange Money, MTN, Moov ; caisse
  d'agent ; compte dédié à une campagne), `mouvements_tresorerie` 🔒 (trait
  `Immuable`), `categories_depense`, `depenses` 📱 (UUID v7).
- `App\Services\Tresorerie`, seule porte des mouvements : entrée, virement (deux jambes
  liées, tout ou rien), avance agent, paiement de dépense, **contre-passation**
  motivée (un virement : ses deux jambes ; une dépense payée passe « annulée »). Comptes
  verrouillés dans l'ordre des id ; **aucun solde négatif** ; date future, montant nul,
  compte désactivé refusés. Solde = somme SQL, pas de colonne.
- `App\Services\Depenses` : **seuil non défini ⇒ validation toujours exigée** ; sous
  le seuil, payée tout de suite ; au-dessus, l'argent sort **à la validation** ;
  `valide_par ≠ cree_par` vérifié dans le service (invariant 5) ; validation sous
  verrou (pas de double paiement) ; refus motivé ; art. 10.3 bloqué sur compte de
  campagne et dépense rattachée à une campagne ; un agent ne paie que depuis sa caisse.
- **Avances aux agents = virement vers leur caisse**, le reste à justifier est le
  solde de cette caisse (table `avances_agents` du modèle supprimée, documenté).
- Écrans : Trésorerie (comptes et soldes, entrée, virement, avance, nouveau compte),
  relevé avec solde courant et contre-passation, Dépenses (saisie avec justificatif
  obligatoire, liste, valider / refuser), catégories dans les Référentiels.
  `App\Support\Montant` : « 1 500 000 » accepté, « 1.500 » et « 1500,5 » **refusés**
  (ambigus).
- Droits : `gerer-tresorerie`, `valider-depenses` (direction, comptable) ;
  `saisir-depenses` (+ agent).

**Vérifié en l'exécutant.**

- `php artisan test` : **194 tests** (150 → 194), 872 assertions ; invariants 3
  (solde = somme, ≥ 0, y compris au-delà de 2³¹ FCFA), 5 et 6 sur les mouvements.
  Larastan 0 (3 corrigées), Pint propre.
- **Livrable de la semaine 3, dans Chrome** (MySQL) : comptable → compte « Caisse
  centrale », apport de 5 000 000 ; catégorie Carburant ; dépense de 600 000 (seuil à
  500 000) avec justificatif → « à valider par un autre », pas de bouton ; **appel
  forcé de `valider` depuis la console du navigateur → refusé par le serveur** (« Vous
  ne pouvez pas valider votre propre dépense ») ; direction → Valider → payée,
  relevé à 4 400 000 ; contre-passation motivée depuis le relevé → 5 000 000, dépense
  « annulée ». **Somme recalculée dans MySQL : 5 000 000**, `cree_par` 3 ≠
  `valide_par` 2.

**Bugs trouvés et corrigés en route.**

- Après un essai refusé (motif trop court), le message d'erreur **restait affiché**
  après l'essai réussi : `resetErrorBag()` en tête des actions.
- « Annulée par Direction » laissait croire que le validateur avait annulé :
  « validée par » / « refusée par ».

**Session interrompue** pendant la dernière série de vérifications. À la reprise,
6 tests échouaient (`RootTagMissingFromViewException` sur la liste des dépenses) :
vue Blade **compilée** tronquée par l'interruption, pas le code. `php artisan
view:clear` → 194/194, Larastan 0, Pint propre. Piège ajouté à `CLAUDE.md`.

**Reste en semaine 3.** Catégories réelles et charges exclues (question 23), seuil de
dépense à confirmer (question 5 ; 500 000 FCFA n'est qu'une valeur d'essai dans la base
locale).

---

## 2026-09-28 — Semaine 2 (en avance) : producteurs, groupes, parcelles, carte QR

**Fait.**

- `producteurs` (UUID v7, D3) : fiche au bureau (identité, pièce, téléphone et Mobile
  Money normalisés à 10 chiffres, village, groupe, photo). **Consentement obligatoire**
  à la création (qui, quand) ; texte provisoire → question 21.
- Code de carte `LYP-000001` attribué par le serveur (`compteurs`, verrou de ligne,
  pas de trou si la création échoue).
- Doublons (`DetectionDoublons`) : même pièce = **refus** ; même téléphone ou même
  Mobile Money (croisés) = **alerte** à confirmer, confirmation inscrite au journal
  (`doublon_confirme`).
- Photo sur le disque **privé**, servie par une route qui vérifie le droit ; l'ancienne
  est effacée au remplacement (minimisation) ; pas de photo orpheline si l'écriture échoue.
- Droits : `voir-producteurs` (direction, agent, comptable, agronome),
  `gerer-producteurs` (direction, agent). Ni admin ni investisseur (données personnelles).
- Liste avec recherche (nom, code, téléphone même tapé « +225 07 … ») et filtre village.
- Groupes de producteurs (écran commun `EcranReferentiel`, sans les onglets ;
  responsable = producteur du village ; nombre de membres).
- `parcelles` (UUID v7) : contour GeoJSON importé (fichier ou texte collé) ;
  `App\Services\Geo\Contour` lit strictement (fermé, ≥ 3 sommets, en Côte d'Ivoire, avec
  un message dédié si latitude/longitude inversées) et calcule la surface sur la sphère
  (méthode turf.js) ; `surface_m2` recalculée par le modèle, **non affectable**, contour
  verrouillé côté Livewire. Dessin SVG sans fond de carte. Sans contour : « non relevée ».
- Carte producteur PDF (dompdf) au format ID-1 sur A4, QR (php-qrcode, correction Q) ne
  contenant **que le code** ; pas de téléphone ni de numéro de pièce sur la carte ;
  chaque impression journalisée (`impression_carte`).
- `Format::hectares()` en entiers.

**Vérifié en l'exécutant.**

- `php artisan test` : **150 tests** (97 → 150), 706 assertions. Larastan 0, Pint propre.
- Le QR généré, **décodé** par le lecteur de php-qrcode, redonne le code (test).
- **Dans Chrome** (agent, MySQL) : fiche sans consentement → refus en français ; avec →
  `LYP-000001`, photo affichée par la route privée, téléphone `+225 07 11 22 33 44` →
  `0711223344` ; parcelle carrée de 150 m collée en GeoJSON → **2,25 ha**, carré dessiné,
  total « 1 ; 2,25 ha relevés » sur la fiche ; 2ᵉ fiche avec le même téléphone →
  alerte nommant `Coulibaly Awa (LYP-000001)`, confirmée → `LYP-000002` ; journal MySQL :
  créations, impression de carte, doublon confirmé, au nom de l'agent.
- Carte : PDF dessiné avec pdf.js (page temporaire, supprimée) et regardé ; puis le QR
  **découpé dans le rendu du PDF** et décodé côté serveur → `LYP-000001`.

**Bugs trouvés et corrigés en route.**

- Choisir un PDF comme photo faisait **planter** le formulaire (aperçu d'un fichier non
  image) : validation dès le choix + `isPreviewable()` ; test de non-régression.
- Carte à **1,1 Mo** (police entière embarquée) → sous-ensemble de police : 30 Ko.
- « LY AGRICOLE » coupé dans le bandeau de la carte (dompdf et `line-height`) → padding.

**Pas vérifié.** Le scan de la carte **imprimée** avec un vrai téléphone (livrable de la
semaine 2 : « la scanner au téléphone ») : à faire à la main. La saisie des 10 producteurs
réels de test attend les vraies zones (question 1).

**Surprise.** Un clic sur « Enregistrer » pendant la requête d'un `wire:model.live`
(choix du village) est ignoré : le bouton est désactivé le temps de la requête.
Normal, mais un utilisateur rapide devra recliquer.

---

## 2026-09-28 — Semaine 1 : installation et écran de connexion

**Fait.**

- MySQL (XAMPP) démarré, base `ly_agricole` créée (utf8mb4).
- Laravel 13.33 installé dans `../ly-agricole-installation` puis déplacé ici sans
  écraser `docs/`, `.claude/`, `CLAUDE.md`, `README.md`, `.editorconfig`. Le
  `CLAUDE.md`/`AGENTS.md` de Laravel (consignes d'installation génériques) et son
  README n'ont pas été repris.
- Livewire 3.8.9 **sans starter kit** (le kit tire Livewire 4 ; choix du développeur),
  Larastan 3.12 (niveau 6, `phpstan.neon`). Tailwind 4.3 était déjà configuré par
  Laravel 13 : seulement `npm install` + `npm run build`.
- `.env` : MySQL, `APP_LOCALE=fr`, `APP_FAKER_LOCALE=fr_FR`, nom « LY AGRICOLE ».
- Connexion : composant `App\Livewire\Auth\Connexion` (e-mail + mot de passe, « rester
  connecté »), 5 essais par e-mail + IP puis blocage temporaire, compte `actif = false`
  refusé avec le même message qu'un mauvais mot de passe. Pas d'inscription publique
  ni de « mot de passe oublié » : les comptes et les mots de passe passent par l'admin.
  Déconnexion en POST. `/` → `/tableau-de-bord` (page provisoire).
- `users` : `name` renommé `nom`, ajout de `telephone` (unique) et `actif`, en
  modifiant directement la migration de base (base neuve, vide).
- Messages en français : `lang/fr/auth.php`, `lang/fr/validation.php` (limité aux
  règles utilisées ; le reste retombe sur l'anglais).
- Seeder de développement : `admin@ly-agricole.test` (mot de passe de la fabrique).

**Vérifié en l'exécutant.**

- `php artisan test` : **11 tests** (0 → 11 ; les 2 exemples de Laravel supprimés),
  45 assertions, tous verts. Après la série, la base MySQL a gardé ses migrations :
  les tests tournent bien sur sqlite en mémoire.
- Parcours réel contre `php artisan serve` + MySQL, par HTTP (script qui rejoue
  exactement l'appel `/livewire/update` du navigateur) : page affichée → mauvais mot
  de passe, message français affiché → bon mot de passe, redirection
  `/tableau-de-bord` → « Bienvenue, Admin Développement. » → déconnexion → le
  tableau de bord renvoie à `/connexion`. `livewire.js` injecté et servi (200).
- Tailwind : les classes des vues (valeurs arbitraires, variantes) sont dans la CSS
  compilée. Pint : rien à corriger. Larastan niveau 6 : 0 erreur.

**Pas vérifié.** Le parcours **dans un vrai navigateur** : l'extension Chrome n'était
pas connectée. À refaire à l'écran (clic, rendu, état de chargement du bouton).

**Surprises.**

- `mysql_error.log` au démarrage : erreurs InnoDB (« log sequence number is in the
  future », tablespaces `immoconnect` introuvables). Elles viennent des autres bases de
  ce XAMPP partagé ; le serveur démarre et `ly_agricole` fonctionne. Non touché.
- Une page a mis 19 s pendant que PHPStan tournait (0,3 s ensuite) : ne pas mesurer
  les temps pendant une analyse.
- Extension PHP `intl` absente (non bloquant pour l'instant ; nécessaire pour
  `Number::format`).

**Premier commit** fait et poussé par le développeur (`4267663`, sur `origin/main`).

### Suite : rôles et gestion des utilisateurs

**Fait.**

- `App\Enums\Role` (admin, direction, comptable, agent, agronome, investisseur), colonne
  `users.role` (nouvelle migration, **sans valeur par défaut** : sans rôle = aucun
  droit, message sur le tableau de bord).
- Droits nommés dans `AppServiceProvider::definirLesDroits()` ; pour l'instant
  `gerer-utilisateurs` (admin). **Pas** de `Gate::before` pour l'admin (séparation des
  tâches). Matrice à valider : question ouverte n° 19.
- Écran `/utilisateurs` (`GestionUtilisateurs`) : liste, création, modification,
  désactivation, nouveau mot de passe. Droit revérifié dans **chaque** action Livewire.
  L'admin ne peut ni se désactiver ni changer son propre rôle.
- Middleware `CompteActif` : un compte désactivé perd sa session ouverte à la requête
  suivante.
- Menu affiché selon les droits ; page 403 en français.
- Seeder : un compte par rôle, `<role>@ly-agricole.test`.

**Vérifié en l'exécutant.**

- `php artisan test` : **28 tests** (11 → 28), 148 assertions. Larastan 0 erreur
  (il a trouvé les propriétés du modèle `User` non déclarées → `@property` ajoutés).
- **Dans Chrome** : mauvais mot de passe → message français ; connexion admin →
  lien « Utilisateurs », liste des 6 comptes ; création d'un compte (mot de passe trop
  court refusé en français, puis accepté) ; déconnexion ; connexion avec le nouveau
  compte agent → pas de lien, `/utilisateurs` → 403 « Accès refusé ».

**Surprises.**

- **Un service worker de VistaResidence contrôle `http://127.0.0.1:8000` dans Chrome**
  (caches `vistimmob-*`) et bloque les navigations. Non touché (il appartient à
  l'autre projet) : tester LY AGRICOLE sur **`http://localhost:8000`**, qui est une autre
  origine. À ajouter aux pièges de `CLAUDE.md`.
- L'outil d'automatisation de Chrome perdait ses frappes juste après un chargement de
  page (aucune requête n'atteignait le serveur, vérifié dans le journal de
  `artisan serve`). Contourné en remplissant les champs par JavaScript dans la page ;
  ce n'est pas un défaut de l'appli.
- Après un 403, l'objet de test Livewire ne peut plus rejouer d'appel : un composant
  neuf par action dans le test.

### Suite : journal d'activité

**Fait.**

- Table `journal_activite` 🔒 (user, action, objet_type/objet_id en texte pour les
  futurs UUID, avant/apres JSON, ip, appareil, at). Pas de `nullOnDelete` sur `user_id`
  (il réécrirait le journal).
- **Protection réutilisable des registres immuables**, pour les registres des semaines
  3 à 6 : trait `App\Models\Concerns\Immuable` (bloque `save`/`update`/`delete`, **y
  compris** `saveQuietly`/`deleteQuietly`, car il remplace `performUpdate` et
  `performDeleteOnModel` au lieu d'écouter des événements) +
  `#[UseEloquentBuilder(BuilderImmuable::class)]` (bloque `query()->update()`,
  `delete()`, `increment()`, `upsert()`…). Le trait refuse de démarrer si l'attribut
  manque. Reste possible : SQL brut via `DB::table()` — ne jamais en écrire sur ces
  tables.
- Trait `Journalise` (sur `User`) : création, modification (seulement les champs
  changés, avant → après), suppression. Attributs `$hidden` écrits « (masqué) ».
  Changement du seul `remember_token` / `updated_at` : pas de ligne (sinon chaque
  déconnexion en ferait une). Écrit dans la même transaction : une opération annulée
  n'a pas de trace.
- Connexion, déconnexion, échec (avec l'adresse tapée, même inconnue) et blocage après
  5 essais sont journalisés.
- `Relation::enforceMorphMap` : `objet_type` = nom court stable (`user`), pas un nom de
  classe. Tout nouveau modèle référencé devra y être déclaré.
- Écran `/journal` (admin et direction, droit `voir-journal`) : filtres action et
  utilisateur (gardés dans l'adresse), 50 lignes par page, valeurs lisibles
  (« oui/non », « (vide) »). Pagination en français.

**Vérifié en l'exécutant.**

- `php artisan test` : **59 tests** (28 → 59), 248 assertions. Invariant 6 testé sur
  10 manières de modifier ou supprimer une ligne. Larastan : 0 erreur (8 corrigées :
  types manquants, appel `static::` à une méthode privée, type générique du builder
  → réglé par l'attribut `UseEloquentBuilder`). Pint : propre.
- **Dans Chrome** (localhost:8000, base MySQL migrée) : déconnexion de l'agent, connexion
  admin, modification du téléphone d'un compte → le journal montre les trois lignes,
  avec auteur, IP et « (vide) → 0700000001 » ; filtre « Connexion » → une seule ligne,
  `?action=connexion` dans l'adresse ; compte direction → lien Journal, pas de lien
  Utilisateurs, `/utilisateurs` → « Accès refusé », `/journal` → sa propre connexion en
  tête.

**Surprise.** La première vérification des valeurs dans le journal a montré du JSON
brut (`"Ancien Nom"`, `true`) : illisible pour la direction ; remplacé par
`JournalActivite::valeurLisible()`.

### Suite : référentiels

**Fait.**

- Tables `zones`, `villages`, `produits`, `campagnes`, `magasins`, `points_collecte`,
  `parametres` (une migration). Pas de suppression : champ `actif`. Tous `Journalise`,
  tous déclarés dans le morph map.
- Écarts avec `MODELE_DE_DONNEES.md`, reportés dans le document : `magasins.capacite_g`
  (grammes, D4) au lieu de `capacite_kg` ; pas de `produits.unite` (tout se pèse).
- Écran commun `EcranReferentiel` (liste, ajout, modification, onglets selon les
  droits) ; sous-classes Zones, Villages, Produits, Magasins, PointsCollecte, Campagnes.
  `peutModifier()`/`actionsLigne()` protégées (une méthode publique Livewire est
  appelable depuis le navigateur). Route de la page gardée dans `$routePage` verrouillé
  (sinon l'onglet actif se perd après une action : la route devient `livewire.update`).
- Campagnes (direction seule) : code `AAAA-AAAA` unique par produit, fin ≥ début, prix
  officiel en FCFA entiers **facultatif** (non annoncé), action « Ouvrir », **une seule
  ouverte par produit** (verrou `lockForUpdate`), produit figé une fois ouverte,
  clôturée non modifiable. Pas de clôture : elle viendra avec le résultat et D6.
- Paramètres (direction seule) : 3 seuils de validation (dépense, achat, prêt), **aucune
  valeur par défaut**, « Non défini » affiché en orange. `Parametre::entier()` rend
  `null` si non défini — au code des semaines 3 à 6 d'exiger alors la validation.
- `App\Support\Format::fcfa()` / `::kg()` : affichage seulement, calcul **en entiers**
  (pas de division flottante), testé au-delà de 2³¹ g.
- Seeder de dev : produits anacarde, karité, tomate ; « Zone de test » / « Village de
  test ». Aucun prix, aucun seuil.

**Vérifié en l'exécutant.**

- `php artisan test` : **97 tests** (59 → 97), 460 assertions ; matrice des droits
  figée par `AccesReferentielsTest` (7 écrans × 6 rôles). Larastan 0 erreur, Pint propre.
- **Dans Chrome** (direction, base MySQL migrée) : zone créée ; village avec GPS ;
  produit Anacarde ; campagne 2026-2027 avec d'abord fin < début → message français,
  puis correcte, puis « Ouvrir » → « Ouverte », prix « Non annoncé » ; magasin de
  250 000 kg → « 250 000 kg » ; seuil de dépense 500 000 FCFA, les deux autres « Non
  défini ». Le journal montre chacune de ces opérations avec son auteur, et l'ouverture
  en « statut : preparation → ouverte ». L'onglet actif reste marqué après une action.

**À améliorer (non bloquant).** Le journal affiche les noms de colonnes et les valeurs
brutes (`capacite_g : 250000000`, `zone_id : 1`) : exact pour un audit, peu parlant pour
la direction. Messages « Zone Zone Chrome : créé(e). » quand le nom contient déjà le type.

**Semaine 1 : définition de « fini ».** Un utilisateur par rôle se connecte et ne voit
que ses écrans (tests + Chrome) ; toute création apparaît au journal (tests + Chrome) ;
le nombre de tests a augmenté (0 → 97). Commit `5b9ab56` sur la branche `semaine-1`.
Reste : les réponses aux questions 1, 5, 19, 20.

---

## 2026-09-25 — Création du dossier projet

**Fait.**

- Lecture de toutes les conversations claude.ai du compte « LY Agricole » (sauf DZTV) :
  cahier des charges du site vitrine, contrat de campagne et ses deux relectures, projet
  maraîchage / poules / bissap (volet France, distinct).
- Cahier des charges de la plateforme rédigé dans le document partagé
  <https://claude.ai/code/artifact/e20c4dc8-2b2f-4808-809a-da343a2a9222>, puis étendu
  (achats, stock, reventes, dépenses, trésorerie, comptabilité) et complété (§10 :
  confirmation SMS, carte QR, séparation des tâches, intrants, données personnelles…).
- Dossier créé : `docs/` (cahier, décisions, modèle de données, plan, questions),
  `CLAUDE.md`, quatre skills sous `.claude/skills/`, dépôt Git initialisé **sans
  commit**.

**Pas encore fait.** Aucun code : Laravel est installé en semaine 1 (samedi 26 sept.).

**À faire avant samedi.** Faire relire le cahier des charges au responsable projet
(le document partagé est privé tant qu'il n'est pas partagé depuis son menu) et obtenir
les réponses bloquantes de `docs/QUESTIONS_OUVERTES.md`.
