# Mise en production — LY AGRICOLE

Procédure de la semaine 12 (plan : « une restauration de sauvegarde réussie ; chaque agent
a fait un achat de test sur son propre téléphone »). Écrite le 2026-09-29, **avant** le
choix de l'hébergement (question 10) et du nom de domaine (question 18) : les parties qui
en dépendent sont marquées ⏳.

## 1. Ce qu'il faut sur le serveur

- PHP 8.3 avec `pdo_mysql`, `zip`, `mbstring`, `gd`, `openssl` ; **opcache** activé
  pour le web.
- MySQL 8 ou MariaDB 10.4+, avec `mysqldump` et `mysql` (sauvegardes).
- Composer 2, Node 20+ (seulement pour construire les fichiers CSS/JS).
- Un serveur web (Nginx ou Apache) dont la racine est **`public/`**, jamais le dossier du
  projet.
- **HTTPS obligatoire** (décision D11) : l'appli terrain refuse un serveur `http://`
  public. Certificat Let's Encrypt ou celui de l'hébergeur. ⏳ nom de domaine.
- Un deuxième disque (ou un stockage externe) pour les sauvegardes. ⏳ hébergeur.

## 2. Installation

```bash
git clone … ly-agricole && cd ly-agricole
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# Éditer .env (section 3), puis :
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Attention (piège de CLAUDE.md)** : avec la configuration en cache, les tests
utiliseraient la vraie base. **Ne jamais lancer `php artisan test` sur le serveur de
production.**

## 3. Le fichier `.env` de production

| Clé | Valeur | Pourquoi |
| --- | --- | --- |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | sinon une erreur affiche des données (requêtes, chemins) |
| `APP_URL` | `https://…` ⏳ | liens des PDF, adresse donnée aux agents |
| `DB_*` | utilisateur MySQL **dédié**, pas `root` | droits limités à `ly_agricole`, plus `CREATE`/`DROP` sur `ly_agricole_verif` (vérification des sauvegardes) |
| `SESSION_ENCRYPT` | `true` | |
| `SESSION_SECURE_COOKIE` | `true` | cookie envoyé seulement en HTTPS |
| `LOG_LEVEL` | `warning` | |
| `SMS_PILOTE` | `journal` tant que le fournisseur n'est pas choisi (question 11) | |
| `SAUVEGARDE_DOSSIER` | dossier sur un **autre disque** | un disque qui lâche ne doit pas emporter base et sauvegardes |
| `SAUVEGARDE_MOT_DE_PASSE` | généré par `php artisan ly:mot-de-passe-sauvegardes`, **noté hors du serveur** | archives chiffrées AES-256 (données personnelles, loi 2013-450) ; sans lui, aucune restauration |
| `SAUVEGARDE_HORS_SITE_DISQUE` ou `SAUVEGARDE_HORS_SITE_DOSSIER` | ⏳ selon l'hébergeur (section 5) | copie de chaque archive hors du serveur |

Une clé déclarée vide (`X=`) n'est pas une clé absente (piège de CLAUDE.md) : le code
filtre `''`, mais mieux vaut supprimer la ligne que la laisser vide.

## 4. Processus qui doivent tourner en permanence

- **File d'attente** (SMS de confirmation) : `php artisan queue:work --tries=3`, relancé
  automatiquement (Supervisor ou service systemd).
- **Planificateur** (sauvegardes) : une tâche cron chaque minute :
  `* * * * * cd /chemin/ly-agricole && php artisan schedule:run >> /dev/null 2>&1`
  - chaque nuit à 02:00 : `ly:sauvegarder` ;
  - chaque dimanche à 03:00 : `ly:verifier-sauvegarde` (restauration complète dans une
    base jetable, comparaison, suppression).

## 5. Sauvegardes et restauration

**Contenu d'une archive** (`ly-agricole-AAAAMMJJ-HHMMSS.zip`) : `base.sql`
(`mysqldump --single-transaction` : instantané cohérent sans arrêter l'application), tous
les fichiers privés (justificatifs, reçus, accords écrits, photos du terrain), et
`manifest.json` : nombre de lignes par table, **sommes des registres** (trésorerie,
stock, remboursements, décaissements, achats, prêts) et empreinte SHA-256 de chaque
fichier. Les 7 dernières archives sont toujours gardées ; au-delà, 30 jours.

**Vérifier** (automatique le dimanche ; à la main quand on veut) :

```bash
php artisan ly:sauvegarder --verifier     # sauvegarde + vérification immédiate
php artisan ly:verifier-sauvegarde        # vérifie la plus récente
```

La vérification refuse une archive altérée (SHA-256), restaure `base.sql` dans
`ly_agricole_verif`, compare comptes et sommes, contrôle que chaque fichier cité par la
base est dans l'archive, puis supprime la base jetable. Elle ne touche **jamais** la
base de production (refus si les deux noms sont égaux). Le résultat est dans
`storage/sauvegardes/derniere-verification.json` et, s'il manque, a échoué ou date de
plus de 8 jours, dans **Rapports → Alertes**.

**Mot de passe des archives** — une seule fois, sur le serveur :

```bash
php artisan ly:mot-de-passe-sauvegardes    # 32 caractères, écrit dans .env, affiché UNE fois
php artisan config:cache
```

Le noter **tout de suite** hors du serveur, en deux exemplaires (coffre, et papier sous
enveloppe chez la direction). Chaque archive note l'**empreinte** du mot de passe qui
l'ouvre (`manifest.json`, lisible sans lui, sans le révéler) : après un changement
(`--remplacer`), on sait quelle archive demande l'ancien — le garder aussi. La commande
refuse d'écraser un mot de passe existant sans `--remplacer` ; une sauvegarde refuse un
mot de passe de moins de 16 caractères. Tant qu'il n'est pas défini : alerte
« Sauvegardes non chiffrées » dans les rapports.

**Copie hors site** : une sauvegarde sur le même serveur ne protège ni du vol, ni de
l'incendie, ni d'un piratage. Après chaque sauvegarde, `ly:sauvegarder` copie l'archive
hors site, la **relit** et compare son SHA-256 (une copie différente est supprimée et
signalée). Conservation hors site : 90 jours, les 7 dernières toujours gardées. Relancer
une copie : `php artisan ly:copier-sauvegarde`. Deux façons, selon l'hébergeur ⏳
(question 10) :

- **stockage objet** (S3 ou compatible, chez l'hébergeur ou ailleurs) :
  `composer require league/flysystem-aws-s3-v3`, un disque `s3` dans
  `config/filesystems.php` (clés dans `.env`), puis `SAUVEGARDE_HORS_SITE_DISQUE=s3` ;
- **dossier** hors du serveur : partage réseau `\\serveur\partage`, disque monté d'un
  second site, dossier synchronisé avec un stockage en ligne :
  `SAUVEGARDE_HORS_SITE_DOSSIER=…`. Le dossier des sauvegardes locales est refusé.

Les archives étant chiffrées, le stockage hors site ne voit jamais les données en
clair. Alertes dans les rapports : « Copie hors site absente », « en échec », ou
« ancienne » (plus de 2 jours).

**Si le serveur est perdu** : sur un nouveau serveur installé (section 2), avec le mot
de passe noté, `php artisan ly:verifier-sauvegarde /chemin/de/la/copie.zip` restaure
directement la copie hors site dans la base jetable et la vérifie ; puis restaurer pour
de vrai ci-dessous.

**Restaurer pour de vrai** (panne, erreur grave) — à deux, jamais seul :

1. Mettre l'application en maintenance : `php artisan down`.
2. Choisir l'archive ; vérifier qu'elle passe `ly:verifier-sauvegarde <archive>`.
3. Sauvegarder l'état actuel, même abîmé : `php artisan ly:sauvegarder`.
4. Extraire l'archive (mot de passe de la section 3) dans un dossier temporaire.
5. `mysql -u … ly_agricole < base.sql`.
6. Recopier `fichiers/` dans `storage/app/private/`.
7. `php artisan up`, puis ouvrir **Rapports** : restant dû, stock et caisses doivent
   correspondre aux sommes du manifeste de l'archive.
8. Les agents renvoient leur file « À envoyer » : ce qui a été saisi après la
   sauvegarde revient, sans doublon (UUID : c'est pour cela que l'envoi est idempotent).

## 6. Appli terrain

- Construire l'APK sur un poste avec Android Studio (question 26, en suspens) :
  `cd terrain && npm ci && npm run build && npx cap sync android`, puis ouvrir
  `terrain/android` dans Android Studio. **Sans** `LY_TERRAIN_DEV=1` : HTTPS seulement.
- À la première connexion, chaque agent saisit l'adresse `https://…` ⏳ du serveur.
- Livrable : chaque agent fait un achat de test sur son propre téléphone, visible au
  bureau (puis refusé : c'est un test).

## 7. Comptes réels et paramètres

- Créer les comptes (écran **Utilisateurs**, admin) : un compte **par personne**, jamais
  partagé ; rôles selon la question 19.
- Désactiver les comptes de démonstration (`*@ly-agricole.test`).
- À définir par la direction avant le premier achat : seuils de validation, règle de
  valorisation des kilos (question 3), prix officiel de la campagne, seuil d'alerte
  d'écart de poids (question 34).

## 8. Gel

Plan : **gel vendredi 18 décembre**. Après le gel, seulement des corrections, chacune
avec son test et une sauvegarde vérifiée avant la mise en ligne.
