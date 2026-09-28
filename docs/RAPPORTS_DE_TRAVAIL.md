# Rapports de travail

Une entrée par session, la plus récente en haut : ce qui a été fait, ce qui a été
**vérifié en l'exécutant** (et comment), ce qui reste, ce qui a surpris.

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

**Reste en semaine 1.** Rôles et politiques (un utilisateur par rôle ne voit que ses
écrans), référentiels (zones, villages, produits, campagnes, magasins, points de
collecte, paramètres), journal d'activité. Premier commit.

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
