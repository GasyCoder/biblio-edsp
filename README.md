# Bibliothèque EDSP

Application interne de gestion de la bibliothèque de l’École de Droit et Sciences Politiques (EDSP) de l’Université de Mahajanga.

Ce dépôt ne contient pas le site institutionnel public de l’EDSP. Il est exclusivement destiné aux opérations de bibliothèque : utilisateurs, étudiants, catalogue, exemplaires, cartes, présences, consultations sur place et prêts.

## Stack technique

- Laravel 13
- PHP 8.4
- MySQL 8 ou MariaDB compatible
- Inertia.js 2
- Vue 3 et TypeScript
- Tailwind CSS 4
- Vite 8
- Pest 4
- Spatie Laravel Permission

L’interface reprend le langage visuel du template Dashwind fourni localement dans `template-inspire`, sans intégrer ce template comme une seconde application.

## Fonctionnalités disponibles

- authentification interne sans inscription publique ;
- profils utilisateurs et réinitialisation du mot de passe ;
- rôles `superadmin`, `secretaire` et `etudiant` ;
- permissions et dashboards adaptés à chaque rôle ;
- création et recherche d’étudiants ;
- import Excel/CSV des étudiants avec prévisualisation, validation humaine et traçabilité des lignes ;
- export Excel du référentiel étudiant ;
- import Excel des ouvrages avec reprise des catégories, auteurs multilignes et quantités ;
- création automatique d’un exemplaire et d’un code unique pour chaque unité importée ;
- export Excel du catalogue et de ses numéros d’inventaire ;
- numéros étudiants automatiques au format `ETU-AA-001` ;
- création et consultation des ouvrages bibliographiques ;
- auteurs multiples sans fusion automatique des titres ;
- modèle séparant ouvrages et exemplaires physiques ;
- numéros d’exemplaires automatiques au format `EDSP-LIV-000001` ;
- codes scannables opaques et uniques ;
- gestion des catégories, auteurs, emplacements et cartes étudiantes ;
- mode clair et sombre persistant ;
- comptoir optimisé pour le scan des cartes et la recherche de secours ;
- pointage des entrées et sorties avec une seule présence ouverte par étudiant ;
- consultations sur place avec scan, restitution et suivi des exemplaires ;
- verrouillage transactionnel empêchant les doubles scans et les sorties prématurées.

Les modules de prêts, import Excel et statistiques avancées seront ajoutés progressivement. Le plan complet est disponible dans [docs/audit-et-plan-technique.md](docs/audit-et-plan-technique.md).

## Prérequis

- PHP 8.4 avec les extensions usuelles Laravel et `pdo_mysql` ;
- Composer 2 ;
- Node.js 22 ou version compatible avec Vite 8 ;
- npm 11 ;
- MySQL 8 ou MariaDB.

Vérifier impérativement que Composer utilise PHP 8.4 :

```bash
php --version
composer check-platform-reqs
```

Si la commande `php` pointe encore vers une ancienne version :

```bash
php8.4 "$(which composer)" install
php8.4 artisan --version
```

## Installation locale

```bash
git clone https://github.com/GasyCoder/biblio-edsp.git
cd biblio-edsp

composer install
cp .env.example .env
php artisan key:generate
npm install
```

Créer ensuite la base MySQL et renseigner `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biblio_edsp
DB_USERNAME=root
DB_PASSWORD=
```

Configurer des mots de passe initiaux robustes avant d’exécuter les seeders :

```dotenv
SEED_SUPERADMIN_PASSWORD="mot-de-passe-unique"
SEED_SECRETAIRE_PASSWORD="mot-de-passe-unique"
SEED_ETUDIANT_PASSWORD="mot-de-passe-unique"
```

Puis initialiser la base et compiler l’interface :

```bash
php artisan migrate --seed
npm run build
```

Les comptes initiaux utilisent par défaut les adresses suivantes :

| Rôle | Adresse |
|---|---|
| Super administrateur | `superadmin@edsp.mg` |
| Secrétaire | `secretaire@edsp.mg` |
| Étudiant de démonstration | `etudiant@edsp.mg` |

Les adresses, noms et mots de passe sont configurables dans `.env`. Ne jamais committer ce fichier.

## Développement

Lancer tous les services de développement :

```bash
composer run dev
```

Ou lancer séparément Laravel et Vite :

```bash
php artisan serve
npm run dev
```

L’application redirige `/` vers `/login`. Le serveur Laravel utilise par défaut `http://127.0.0.1:8000`.

## Tests et qualité

Les tests utilisent une base MySQL séparée nommée `biblio_edsp_testing`. La créer avant la première exécution :

```sql
CREATE DATABASE biblio_edsp_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Exécuter les contrôles :

```bash
php artisan test --compact
php vendor/bin/pint --test
npm run build
composer validate --strict
```

SQLite ne remplace pas MySQL dans cette application : certains tests dépendent des verrous, contraintes et colonnes générées MySQL.

## Rôles

### Superadmin

Accès complet à l’administration, aux utilisateurs, référentiels, paramètres, statistiques et journaux d’audit.

### Secrétaire

Accès aux opérations du comptoir : recherche étudiant, catalogue, cartes, scans, présences, consultations et prêts selon les permissions attribuées.

### Étudiant

Accès en lecture au catalogue et, lorsque les modules correspondants seront actifs, à ses historiques personnels et restrictions.

L’autorisation est toujours vérifiée côté serveur. Masquer un menu dans Vue ne remplace jamais les middlewares, Gates et Policies Laravel.

## Numérotation interne

Les numéros internes sont produits par des séquences transactionnelles verrouillées en base. Ils ne doivent jamais être saisis manuellement ni calculés à partir du nombre de lignes.

Formats actuellement actifs :

- étudiant : `ETU-AA-001` ;
- exemplaire : `EDSP-LIV-000001`.
- pointage : `EDSP-PTG-AAAAMMJJ-000001` ;
- consultation : `EDSP-CST-AAAAMMJJ-000001`.

## Fichiers sensibles et références locales

Les éléments suivants sont exclus de Git :

- `.env` ;
- journaux et fichiers temporaires Laravel ;
- classeurs Excel placés dans `storage/app/imports/reference` ;
- template visuel local `template-inspire`.

Les fichiers importés doivent rester sur un disque privé et ne doivent pas être exposés directement par le serveur web.

### Import des classeurs d’ouvrages de référence

Les classeurs placés dans `storage/app/imports/reference` sont proposés directement dans **Ouvrages → Importer**. Ils ne sont jamais importés automatiquement : une analyse affiche d’abord les ouvrages, auteurs et quantités détectés, puis le superadministrateur valide les lignes correctes. Une quantité `N` crée un ouvrage bibliographique et `N` exemplaires possédant chacun son propre numéro d’inventaire et code scannable.

La secrétaire peut également envoyer un autre fichier `.xlsx`, `.xls` ou `.csv` depuis ce même écran. Les lignes sans auteur ou sans quantité fiable sont conservées comme erreurs et doivent être corrigées dans le classeur avant une nouvelle analyse.

## Contribution

Avant chaque push :

1. exécuter Pest, Pint et le build TypeScript ;
2. vérifier les permissions et l’absence de données personnelles dans le diff ;
3. créer des commits regroupés par fonctionnalité ;
4. ouvrir une pull request vers `master`.
