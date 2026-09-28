# CLAUDE.md

Guide pour Claude Code (et tout développeur) sur ce dépôt.

## Ce qu'est ce projet

Plateforme de gestion de **LY AGRICOLE**, entreprise agricole ivoirienne (créée en
2026) : exploitation, négoce et commercialisation d'anacarde, de beurre de karité, de
tomate et d'autres produits. Elle suit **chaque franc et chaque kilo à chaque stade** :
prêts de campagne aux producteurs et leur remboursement en argent ou en kilos, achats
bord-champ, stock par lot, reventes, dépenses, caisses et Mobile Money, rendements par
parcelle ; plus tard, diagnostic des cultures par IA locale et conseil de traitements.

À lire avant de coder, dans cet ordre :

1. `docs/CAHIER_DES_CHARGES.md` — quoi et pourquoi.
2. `docs/DECISIONS.md` — les choix d'architecture et leurs raisons (D1 à D10).
3. `docs/MODELE_DE_DONNEES.md` — tables et **invariants** à tester.
4. `docs/PLAN_IMPLEMENTATION.md` — la semaine en cours et sa définition de « fini ».
5. `docs/RAPPORTS_DE_TRAVAIL.md` — où s'est arrêtée la dernière session.

Rôles humains : le **responsable projet** (côté métier, décide du périmètre) et le
**développeur** (l'utilisateur de Claude Code). Les questions métier non tranchées sont
dans `docs/QUESTIONS_OUVERTES.md` : ne pas les trancher à leur place, poser la
question ou prendre la solution la plus prudente et la noter.

## Skills du projet (`.claude/skills/`)

| Skill | À charger quand |
| --- | --- |
| `ly-agricole-metier` | on touche au vocabulaire ou aux règles de la filière (campagne, anacarde, KOR, pisteur, contrat de campagne) |
| `ly-agricole-argent-et-kilos` | on écrit ou modifie tout ce qui compte de l'argent ou des poids : prêts, achats, stock, trésorerie, dépenses |
| `ly-agricole-ia-conseil` | on touche au diagnostic par IA, au LLM local ou aux recommandations de traitements |
| `ly-agricole-terrain-hors-ligne` | on touche à l'appli terrain, à l'API ou à `/api/sync` |

## Pile

- Back-office : **Laravel 13 + Livewire 3 + Tailwind CSS 4**, MySQL (XAMPP en local),
  PHP 8.3. Tests **PHPUnit** (pas Pest).
- Appli terrain : **SvelteKit statique + Dexie (IndexedDB) + Capacitor Android**, dans
  `terrain/` — PAS du Livewire (voir D2).
- Service IA (phase 3) : Python FastAPI + Ollama, dans `ia/`.

## Commandes

À compléter en semaine 1, une fois Laravel installé.

**Installation (semaine 1)** : `composer create-project` refuse un dossier non vide,
et celui-ci contient déjà `docs/`, `.claude/`, `CLAUDE.md`, `README.md`,
`.editorconfig` et `.git/`. Installer dans un dossier voisin temporaire
(`../ly-agricole-installation`), en tâche de fond (Composer dépasse 10 min ici), puis
déplacer son contenu ici **sans écraser** ces fichiers : fusionner `README.md` et
`.editorconfig` à la main, garder notre `CLAUDE.md`, reprendre le `.gitignore` de
Laravel.

```bash
php artisan config:clear               # TOUJOURS avant une série de tests (voir pièges)
php artisan test                       # suite complète
php artisan test --filter=NomDuTest
vendor/bin/pint                        # style
vendor/bin/phpstan analyse --memory-limit=1G   # Larastan niveau 6 (phpstan.neon), plusieurs minutes ici
npm run build                          # Vite + Tailwind 4 (config dans resources/css/app.css)
php artisan serve                      # http://127.0.0.1:8000
```

Installé le 2026-09-28 : Laravel 13.33, Livewire 3.8.9 (sans starter kit),
Tailwind 4.3, PHPUnit 12.5, Larastan 3.12. Base MySQL `ly_agricole` (root, sans mot
de passe) ; les tests tournent sur sqlite en mémoire (`phpunit.xml`).

## Règles non négociables

- **Argent en entiers de FCFA, poids en grammes** (`BIGINT`). Jamais de `float`.
- **Registres immuables** : mouvements de trésorerie, de stock, d'intrants,
  remboursements, décaissements, journal — ni `update` ni `delete` ; on
  contre-passe avec un motif.
- **Qui saisit ne valide pas** (`valide_par ≠ cree_par`), vérifié par le code et testé.
- **Tout ce qui est créable hors ligne a un UUID v7 généré sur le téléphone** et
  l'envoi est idempotent.
- **L'IA ne choisit jamais un produit ni une dose** : seulement le référentiel validé.
- Noms métier **en français sans accents** (`Pret`, `Achat`, `MouvementStock`) ; le
  vocabulaire du framework reste en anglais.

## Conventions

- 4 espaces, LF, UTF-8 ; YAML en 2 espaces.
- Tests marqués avec l'attribut `#[Test]`, **jamais** l'annotation `/** @test */` :
  PHPUnit 12 ignore les annotations et les tests disparaissent sans échouer. Après
  toute mise à jour des outils de test, comparer le **nombre** de tests exécutés.
- Rédiger le compte rendu de chaque session dans `docs/RAPPORTS_DE_TRAVAIL.md` : le
  terminal tronque, le fichier reste.
- Une vérification n'est faite que lorsqu'on a **exécuté le vrai parcours** : un test
  vert, un audit ou un grep indiquent où regarder, ils ne concluent pas.

## Pièges connus (hérités de Masadora / VistaResidence, même poste, même pile)

Chacun a coûté du temps sur l'autre projet ; ils s'appliquent ici tels quels.

- **Config en cache ⇒ les tests vident la base MySQL.** Si
  `bootstrap/cache/config.php` existe, `phpunit.xml` (sqlite en mémoire) est ignoré et
  `RefreshDatabase` s'exécute sur la vraie base. Vérifier et lancer
  `php artisan config:clear` **avant** toute série de tests.
- **MySQL n'est pas démarré par défaut** sur ce poste. « Connection refused » en début
  de session = démarrer `mysqld`, pas un bug. Et un `try/catch` peut faire passer ça pour
  une base vide.
- **Une clé `.env` déclarée VIDE n'est pas une clé absente.** `env('X')` rend `''`, pas
  `null`. Sur Masadora, `MYSQL_ATTR_SSL_CA=` a cassé toutes les requêtes et
  `MAIL_URL=` a empêché l'envoi de tout mail. Filtrer `''` autant que `null` dans les
  fichiers `config/`.
- **Option Artisan = deux tirets.** `Artisan::call('cmd', ['--batch' => 500])` ; sans
  les tirets c'est un argument et la commande ne démarre pas.
- **Livewire : pas de propriété publique `$message`** dans un composant dont la vue
  utilise `@error` (Blade la détruit). Même risque avec `$errors`, `$slot`,
  `$attributes`.
- **Livewire : 18 noms de méthode sont inatteignables depuis la vue** (`upload`, `get`,
  `set`, `call`, `on`, `js`, `watch`, `dispatch`…) : `wire:click="upload"` appelle
  l'outil interne de Livewire, pas votre méthode, sans rien journaliser. Idem pour une
  méthode qui porte le nom d'une propriété publique. Les tests ne le voient pas
  (`->call()` contourne le proxy).
- **Livewire : sans réseau, rien ne se passe et rien ne le signale.** Raison de D2.
- **Un iframe dans un composant Livewire a besoin de `wire:ignore`**, sinon chaque
  rendu le recharge.
- **Git Bash réécrit les chemins d'URL** : `/entreprise` devient
  `C:/Program Files/Git/entreprise`. Préfixer `MSYS_NO_PATHCONV=1`.
- **Un chemin POSIX (`/tmp/...`) passé à PHP n'écrit nulle part** (PHP Windows le lit
  `C:\tmp\...`). Utiliser un chemin que les deux comprennent (`mktemp -p .`).
- **Le heredoc Bash mange les antislashs** des chemins Windows : écrire le script dans
  un fichier, puis l'exécuter.
- **Composer est lent ici (plus de 10 minutes)** : le lancer en tâche de fond ; le tuer
  laisse des `.tmp` qui bloquent la suite.
- **Une balise `<x-…/>` dans un commentaire CSS/JS/HTML est compilée** : seul
  `{{-- … --}}` la protège.
- **Ne jamais construire un nom de classe Tailwind par concaténation** : il ne serait
  pas généré.
- **`assertSessionHas` ne voit pas un message flash Livewire** : vérifier ce que la vue
  affiche (`assertSee`).
