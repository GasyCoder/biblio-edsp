# Audit et plan technique — biblio-edsp

Date de l'audit : 15 juillet 2026. Périmètre : application interne de gestion de la bibliothèque de l'EDSP uniquement. Ce document est une conception ; aucune migration ni aucun module métier n'est créé.

## 1. Audit exact de l'existant

### État vérifié

| Élément | Constat |
|---|---|
| Projet | Squelette Laravel presque vierge, sans métadonnées Git dans le dossier audité |
| Framework installé | Laravel 13.20.0 |
| PHP | Audit initial : CLI par défaut en 8.3.32. PHP 8.4 est disponible et la contrainte Composer a été alignée sur `^8.4` pendant la phase socle |
| Front-end | Audit initial : Inertia, Vue 3 et TypeScript absents. Ils ont été ajoutés pendant la phase socle, avec Vite 8 et Tailwind CSS 4 |
| Entrée JS | Audit initial : `resources/js/app.js` vide. Le socle utilise désormais `resources/js/app.ts` et des pages Vue/Inertia TypeScript |
| CSS | Tailwind 4 configuré, police Instrument Sans chargée via le plugin Vite |
| Authentification | Modèle `User` Laravel par défaut ; aucune interface, aucun contrôleur d'authentification, aucun rôle/permission |
| Modèles/contrôleurs | Seulement `User` et le contrôleur de base |
| Routes | `/` rend `welcome`; routes framework `/up` et stockage. Aucune route métier/API |
| Base ciblée | MySQL 8.0.46, base `biblio_edsp`, 10 tables Laravel par défaut, aucune donnée utilisateur |
| Migrations appliquées | Les 3 migrations Laravel par défaut : utilisateurs/sessions, cache, files d'attente |
| Pilotes | DB MySQL ; sessions, cache et queue en base ; fichiers locaux ; mail en journal |
| Tests | Pest 4.7.5 et plugin Laravel 4.1.0 ; uniquement les deux exemples du squelette |
| Environnement | `local`, debug actif, URL localhost, fuseau UTC, locale anglaise |
| Dépendances manquantes | Adaptateur Inertia Laravel, Vue 3, adaptateur Inertia Vue, TypeScript, plugin Vue Vite, Ziggy éventuel, bibliothèque RBAC, lecture Excel, QR/code-barres |
| Données Excel | Fichiers remplacés et valides : 3 classeurs, 4 feuilles, en-têtes ligne 3, 878 lignes maximales cumulées |

### Écarts à corriger avant le métier

1. Passer l'exécution et la contrainte Composer à PHP 8.4.
2. Installer/configurer Inertia + Vue 3 + TypeScript en conservant le squelette existant.
3. Régler `APP_NAME`, `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=fr` et `APP_TIMEZONE=Indian/Antananarivo` (stockage recommandé en UTC, affichage local).
4. Choisir et figer les bibliothèques : RBAC (Spatie Permission recommandé), Excel (PhpSpreadsheet ou Laravel Excel), QR et Code 128.
5. Fournir les vrais classeurs Excel ; les trois fichiers actuels ne sont pas des classeurs exploitables.
6. Ajouter une base de test séparée. Ne jamais lancer Pest contre `biblio_edsp`.

## 2. Schéma complet proposé

Conventions : `BIGINT UNSIGNED` pour les clés, `DATETIME(6)` pour les événements, timestamps Laravel, clés étrangères explicites. Les valeurs métier sont stockées en chaînes et castées vers des enums PHP, afin d'éviter les contraintes de migration des `ENUM` MySQL. Les documents importés et photos restent sur disque privé ; la base conserve leur chemin.

### Identité et autorisation

- `users`: `id`, `name`, `email` nullable, `username` nullable, `password`, `is_active`, `last_login_at`, `email_verified_at`, `remember_token`, timestamps. Au moins email ou username est exigé par validation applicative.
- Tables Spatie : `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, avec `guard_name=web`. Seuls `superadmin`, `secretaire`, `etudiant` sont seedés.
- `students`: `id`, `user_id` nullable, `registration_number`, `academic_number` nullable, `last_name`, `first_name`, `gender` nullable, `birth_date` nullable, `level` nullable, `program` nullable, `academic_year` nullable, `phone` nullable, `email` nullable, `photo_path` nullable, `status`, `restriction_reason` nullable, timestamps, soft delete.
- `student_cards`: `id`, `student_id`, `card_number`, `type`, `symbology`, `status`, `issued_at`, `expires_at` nullable, `replaced_by_id` nullable (auto-FK), `created_by`, timestamps.

### Catalogue

- `categories`: `id`, `parent_id` nullable (auto-FK), `name`, `slug`, `description` nullable, `is_active`, timestamps, soft delete.
- `authors`: `id`, `display_name`, `last_name` nullable, `first_name` nullable, timestamps, soft delete.
- `books`: `id`, `title`, `subtitle` nullable, `category_id` nullable, `publication_year` nullable, `publisher` nullable, `summary` nullable, `isbn` nullable, `keywords` JSON nullable, `language` nullable, `edition` nullable, timestamps, soft delete. Aucun dédoublonnage automatique sur titre/ISBN pendant un import.
- `author_book`: `book_id`, `author_id`, `position`, timestamps ; PK composée `(book_id, author_id)`.
- `locations`: `id`, `code`, `name`, `description` nullable, `is_active`, timestamps, soft delete.
- `copies`: `id`, `book_id`, `location_id` nullable, `inventory_number`, `barcode_value`, `barcode_symbology`, `condition`, `status`, `registered_at`, `source_import_id` nullable, `source_import_row_id` nullable, `notes` nullable, `lock_version` défaut 0, timestamps, soft delete.

### Pointage et consultation

- `visits`: `id`, `visit_number`, `student_id`, `entered_at`, `entered_by`, `exited_at` nullable, `exited_by` nullable, `status`, `forced_closed_at` nullable, `forced_closed_by` nullable, `force_reason` nullable, timestamps.
- `consultation_sessions`: `id`, `consultation_number`, `visit_id`, `student_id`, `opened_at`, `opened_by`, `closed_at` nullable, `closed_by` nullable, `status`, `forced_closed_at` nullable, `forced_closed_by` nullable, `force_reason` nullable, timestamps.
- `consultation_items`: `id`, `consultation_session_id`, `copy_id`, `scanned_at`, `scanned_by`, `returned_at` nullable, `returned_by` nullable, `status`, timestamps. Une ligne représente l'affectation d'un exemplaire à une session, pas chaque scan.

### Prêts

- `loans`: `id`, `loan_number`, `student_id`, `loaned_at`, `due_at`, `closed_at` nullable, `status`, `created_by`, `closed_by` nullable, `notes` nullable, timestamps.
- `loan_items`: `id`, `loan_id`, `copy_id`, `loaned_at`, `due_at`, `returned_at` nullable, `loaned_by`, `returned_by` nullable, `status`, `condition_out`, `condition_in` nullable, `notes` nullable, timestamps.

### Imports, audit et configuration

- `imports`: `id`, `original_filename`, `stored_path`, `sha256`, `status`, `total_rows`, `valid_rows`, `invalid_rows`, `created_books`, `created_copies`, `uploaded_by`, `validated_by` nullable, `validated_at` nullable, `committed_by` nullable, `committed_at` nullable, timestamps.
- `import_rows`: `id`, `import_id`, `sheet_name`, `row_number`, `raw_data` JSON, `normalized_data` JSON nullable, `errors` JSON nullable, `warnings` JSON nullable, `status`, `book_id` nullable, `copies_expected` nullable, `copies_created` défaut 0, timestamps.
- `audit_logs`: `id`, `actor_id` nullable, `event`, `auditable_type`, `auditable_id` nullable, `old_values` JSON nullable, `new_values` JSON nullable, `metadata` JSON nullable, `ip_address` nullable, `user_agent` nullable, `occurred_at`. Append-only au niveau applicatif.
- `settings`: `id`, `key`, `value` JSON nullable, `type`, `group`, `is_public` défaut faux, `updated_by` nullable, timestamps.
- `number_sequences`: `id`, `key`, `scope`, `current_value`, timestamps. Une ligne par couple type/période, par exemple `(visit, 20260715)`.

### Relations Eloquent

- `User belongsToMany Role/Permission`; `User hasOne Student`; `Student belongsTo User` nullable.
- `Student hasMany StudentCard, Visit, ConsultationSession, Loan`; `StudentCard belongsTo Student`, `belongsTo replacedBy`, `belongsTo creator`.
- `Category belongsTo parent`, `hasMany children`, `hasMany Book`; `Book belongsTo Category`, `belongsToMany Author`, `hasMany Copy`; `Author belongsToMany Book`.
- `Location hasMany Copy`; `Copy belongsTo Book, Location, sourceImport, sourceImportRow`; `Copy hasMany ConsultationItem, LoanItem`.
- `Visit belongsTo Student, enteredBy, exitedBy`; `Visit hasOne ConsultationSession`.
- `ConsultationSession belongsTo Visit, Student, openedBy, closedBy`; `hasMany ConsultationItem`.
- `ConsultationItem belongsTo ConsultationSession, Copy, scannedBy, returnedBy`.
- `Loan belongsTo Student, createdBy, closedBy`; `hasMany LoanItem`; `LoanItem belongsTo Loan, Copy, loanedBy, returnedBy`.
- `Import belongsTo uploadedBy/validatedBy/committedBy`; `hasMany ImportRow`; `ImportRow belongsTo Import, Book`; `AuditLog morphTo auditable`, `belongsTo actor`.

## 3. Enums PHP

| Enum | Valeurs |
|---|---|
| `StudentStatus` | `active`, `inactive`, `suspended`, `graduated` |
| `CardType` | `student`, `library` |
| `CardStatus` | `active`, `suspended`, `expired`, `replaced` |
| `BarcodeSymbology` | `qr`, `code128` |
| `CopyStatus` | `available`, `in_consultation`, `borrowed`, `damaged`, `lost`, `archived` |
| `CopyCondition` | `new`, `good`, `fair`, `poor` |
| `VisitStatus` | `open`, `closed`, `forced_closed` |
| `ConsultationStatus` | `open`, `closed`, `forced_closed` |
| `ConsultationItemStatus` | `in_use`, `returned`, `forced_return` |
| `LoanStatus` | `open`, `closed`, `overdue`, `forced_closed` |
| `LoanItemStatus` | `borrowed`, `returned`, `overdue`, `lost` |
| `ImportStatus` | `uploaded`, `parsing`, `review`, `validated`, `committing`, `completed`, `failed`, `cancelled` |
| `ImportRowStatus` | `pending`, `valid`, `warning`, `invalid`, `approved`, `imported`, `rejected` |
| `SettingType` | `string`, `integer`, `boolean`, `json`, `date` |

Chaque enum implémente un libellé français séparé de sa valeur persistée. Les transitions de statut sont centralisées dans les services, jamais acceptées directement depuis un formulaire.

## 4. Contraintes uniques et index

### Uniques

- `users.email`, `users.username` (les deux nullables, unicité si renseignés).
- `students.registration_number`, `students.academic_number` nullable, `students.user_id` nullable.
- `student_cards.card_number`; une seule carte active par étudiant doit être garantie par une colonne générée MySQL nullable `active_student_id = IF(status='active', student_id, NULL)` avec index unique. Alternative portable : table de verrou dédiée, moins simple.
- `categories.slug`, `locations.code`, `copies.inventory_number`, `copies.barcode_value`.
- `visits.visit_number`, `consultation_sessions.consultation_number`, `consultation_sessions.visit_id`, `loans.loan_number`.
- `consultation_items(consultation_session_id, copy_id)`, `loan_items(loan_id, copy_id)`.
- `settings.key`, `number_sequences(key, scope)`, `import_rows(import_id, sheet_name, row_number)`.

### Index opérationnels

- Étudiants : `(status, last_name, first_name)`, `academic_number`, préfixes/recherche normalisée du nom ; envisager FULLTEXT uniquement après mesure.
- Cartes : `(student_id, status)`, `(status, expires_at)`.
- Livres : `(category_id, title)`, `isbn`, `publication_year`; auteurs sur `display_name`; exemplaires `(book_id, status)`, `(location_id, status)`, `(source_import_id)`.
- Visites : `(student_id, status)`, `(entered_at)`, `(status, entered_at)`.
- Sessions : `(student_id, status)`, `(status, opened_at)` ; items `(copy_id, status)`, `(status, returned_at)`.
- Prêts : `(student_id, status)`, `(status, due_at)` ; items `(copy_id, status)`, `(status, due_at)`.
- Imports : `(status, created_at)` ; audit `(auditable_type, auditable_id, occurred_at)`, `(actor_id, occurred_at)`, `(event, occurred_at)`.

### Invariants concurrents

MySQL n'offre pas d'index unique partiel. Pour garantir, et pas seulement valider, « une présence/session/consultation active », ajouter des colonnes générées nullables et uniques :

- `visits.open_student_id = IF(status='open', student_id, NULL)` unique ;
- `consultation_sessions.open_student_id = IF(status='open', student_id, NULL)` unique ;
- `consultation_items.active_copy_id = IF(status='in_use', copy_id, NULL)` unique ;
- `loan_items.active_copy_id = IF(status IN ('borrowed','overdue'), copy_id, NULL)` unique.

Les services verrouillent aussi `students` et `copies` avec `SELECT ... FOR UPDATE`. Les contraintes DB restent le dernier rempart.

## 5. Génération des numéros

Service unique `NumberGenerator` dans une transaction : calcul du `scope` (année, jour ou global), création initiale atomique de la séquence si absente, lecture de la ligne `number_sequences` avec `lockForUpdate()`, incrément puis formatage. La création de l'entité métier et la consommation du numéro sont dans la même transaction ; un rollback ne consomme rien. Une contrainte unique sur le numéro final couvre toute erreur résiduelle, avec nouvelle tentative limitée sur deadlock/duplicate.

| Type | Clé/scope | Format |
|---|---|---|
| Étudiant | `student` / année locale | `ETU-{AA}-{001}` |
| Exemplaire | `copy` / `global` | `EDSP-{CODE-CATÉGORIE}-{0001}` |
| Pointage | `visit` / date locale | `EDSP-PTG-{AAAAMMJJ}-{000001}` |
| Consultation | `consultation` / date locale | `EDSP-CST-{AAAAMMJJ}-{000001}` |
| Prêt | `loan` / date locale | `EDSP-PRT-{AAAAMMJJ}-{000001}` |

La date de portée est calculée en `Indian/Antananarivo`. Ne jamais utiliser `COUNT(*)`, `MAX(id)` ni un identifiant saisi par l'utilisateur.

## 6. QR code et code-barres

- La valeur canonique scannée est un identifiant opaque et immuable avec préfixe/version/type et aléa, par exemple `EDSP:CARD:1:<ULID>` ou `EDSP:COPY:1:<ULID>`. Elle ne contient aucune donnée personnelle.
- Pour les cartes : QR par défaut, Code 128 possible pour compatibilité avec le matériel existant. Pour les exemplaires : Code 128 lisible et QR optionnel contenant exactement la même valeur canonique.
- La base stocke la valeur et la symbologie, pas l'image. SVG/PDF est généré à la demande côté serveur, échappé, avec texte humain (`registration_number` ou `inventory_number`).
- Impression A4/étiquettes avec gabarits calibrés, marge, taille minimale et page test. Les scans sont traités comme du clavier + Entrée ; aucune dépendance obligatoire à la caméra.
- Réimprimer conserve la valeur. Remplacer une carte invalide l'ancienne et crée une nouvelle valeur. Les recherches de secours utilisent matricule, numéro interne et identité.

## 7. Matrice rôles/permissions

Les permissions sont atomiques (`resource.action`), affectées aux rôles par seeder ; les Policies restent l'autorité. Le superadmin reçoit `*` via `Gate::before` et toutes ses actions sensibles sont auditées.

| Domaine | Superadmin | Secrétaire | Étudiant |
|---|---|---|---|
| Tableau de bord | complet/statistiques | opérationnel | personnel |
| Utilisateurs/rôles/permissions | CRUD/affectation | — | modifier son mot de passe/profil limité |
| Étudiants | CRUD, activer/suspendre | voir/rechercher, mise à jour autorisée | voir son dossier |
| Cartes | CRUD, suspendre/remplacer | scanner, créer/imprimer/remplacer selon droit | voir son statut |
| Catalogue | CRUD + archive | voir/créer/modifier selon droits, pas suppression définitive | voir/rechercher/disponibilité |
| Exemplaires/emplacements | CRUD, changer tout statut | CRUD opérationnel, imprimer codes | disponibilité seulement |
| Présences | voir/créer/clôturer/forcer | scanner, entrée, sortie normale | son historique |
| Consultations | voir/ouvrir/ajouter/retour/clôturer/forcer | flux complet hors forçage | son historique |
| Prêts | complet/forcer | créer, retourner, clôturer autorisé | son historique/retards |
| Imports | charger, valider, engager, annuler | charger/préparer/voir selon droit | — |
| Rapports/statistiques | tous + export | opérationnels | personnels uniquement |
| Paramètres/audit | gérer/voir | — (hors paramètres d'impression autorisés) | — |

Permissions proposées : `users.*`, `roles.*`, `permissions.*`, `students.view/create/update/delete/suspend`, `cards.view/create/update/replace/suspend/print/scan`, `books.*`, `authors.*`, `categories.*`, `copies.*`, `locations.*`, `visits.view/check_in/check_out/force_close`, `consultations.view/open/add_copy/return_copy/close/force_close`, `loans.view/create/return/close/force_close`, `imports.view/upload/review/validate/commit/cancel`, `reports.operational/statistics/export`, `settings.manage`, `audit.view`.

## 8. Workflow complet du pointage

1. La page charge le focus sur le champ de scan ; Entrée soumet la valeur normalisée, avec idempotency key.
2. `CardLookupService` cherche une carte exacte, puis vérifie statut actif, expiration et étudiant actif. Échec : message clair et recherche de secours, sans créer de visite.
3. `VisitService::checkIn` démarre une transaction, verrouille étudiant et présence ouverte, refuse doublon, génère le numéro et crée la visite avec acteur/heure serveur.
4. La réponse affiche photo, identité, statut, heure d'entrée et session/livres éventuels ; le focus retourne au scan.
5. Un second scan de la carte avec présence ouverte propose/engage la sortie selon l'écran et la politique choisie ; ne jamais basculer silencieusement en cas d'ambiguïté.
6. `checkOut` verrouille visite, session et items. Si un item n'est pas restitué, refus avec liste des ouvrages.
7. Si tout est rendu, clôture éventuelle de la session, puis `exited_at`, acteur et statut de visite dans la même transaction.
8. `forceCheckOut`, réservé superadmin, exige un motif non blanc, marque session/items/visite de façon explicite et écrit l'audit dans la transaction.
9. Toute soumission répétée renvoie le résultat existant via clé d'idempotence ou invariant unique, sans double pointage.

## 9. Workflow complet de consultation sur place

1. Depuis une visite ouverte, ouvrir une session ; verrouiller visite/étudiant et refuser une autre session ouverte.
2. Scanner un exemplaire par valeur exacte. Verrouiller `copies`; refuser `borrowed`, `in_consultation`, `lost`, `archived` et, selon politique, `damaged`.
3. Vérifier qu'il n'existe aucun item actif/prêt actif, insérer l'item puis passer l'exemplaire à `in_consultation`, atomiquement. Un nouveau scan dans la même session retourne « déjà ajouté » sans doublon.
4. Afficher immédiatement ouvrage, inventaire, heure et liste active ; rendre le champ au focus.
5. Au retour, scanner l'exemplaire ; verrouiller copie + item actif, renseigner `returned_at/by`, passer item à `returned`, copie à `available` (ou `damaged` si état signalé), atomiquement.
6. Quand aucun item actif ne reste, proposer/clôturer la session. La clôture normale est impossible sinon.
7. La sortie est ensuite autorisée. Une fermeture forcée suit la voie superadmin avec motif et audit ; elle ne doit pas masquer une copie manquante : statut explicite à décider (`lost` ou `damaged`) lors du forçage.

## 10. Stratégie d'import Excel

### Pipeline

1. Upload privé avec validation extension + signature ZIP/XLSX, taille, antivirus si disponible ; calcul SHA-256 et création d'`imports`.
2. Job de lecture en flux/chunks, jamais tout le classeur en mémoire. Une ligne `import_rows` par feuille/numéro avec valeurs brutes intactes.
3. Détection/association assistée des en-têtes (titre, auteur, catégorie, édition, éditeur, année, ISBN, quantité, emplacement) ; aucune supposition irréversible.
4. Normalisation séparée : espaces/Unicode, nombres, année, ISBN, auteur(s), quantité. Conserver brut et normalisé.
5. Quantité absente, zéro, négative, décimale, formule ambiguë ou cellule fusionnée : `invalid`/`warning`, validation humaine obligatoire.
6. Prévisualisation avec filtres erreurs/avertissements et correction humaine. Aucun livre n'est créé à cette étape.
7. Validation figée, puis engagement par lots idempotents. Chaque ligne crée toujours son propre `book`, même titre ou ISBN identique, puis exactement N `copies` avec numéros/codes uniques.
8. Transaction par ligne ou lot borné ; compteurs et liens `book_id`/copies permettent reprise sans duplication. Échec partiel visible et relançable.
9. Rapport final : lignes, erreurs, ouvrages, exemplaires, provenance. Fichier source conservé selon politique de rétention.

### Profilage factuel des fichiers reçus

- Classeur historique : feuille `Ouvrages ` de 234 lignes × 7 colonnes et feuille `Thèses` de 3 lignes × 7 colonnes.
- Classeur « Saisie RJB S Glory » : feuille `Feuille1` de 371 lignes × 7 colonnes.
- Classeur « Saisie Amed » : feuille `Nouveau ouvrage EDSP UMG` de 270 lignes × 9 colonnes, dont deux colonnes supplémentaires sans en-tête métier.
- Les en-têtes utiles sont en ligne 3 : numéro, catégorie, titre, année/maison d'édition, auteur, quantité et numéro d'enregistrement.
- Des catégories sont omises sur les lignes de continuation, les quantités mélangent nombres et chaînes avec zéros initiaux, et certaines lignes portent seulement un auteur. Le mapping devra donc gérer la propagation contrôlée de catégorie et distinguer ligne d'ouvrage, continuation d'auteur et ligne ambiguë, avec revue humaine.

## 11. Routes prévues

Toutes sont sous `auth`, `verified` si retenu, permission/Policy et limitation de débit des scans. Les mutations utilisent POST/PATCH/DELETE et protection CSRF.

```text
GET    /dashboard
GET    /scan                                      scan.index
POST   /scan/cards                                scan.cards.lookup
POST   /scan/copies                               scan.copies.lookup

GET    /students                                  students.index
POST   /students                                  students.store
GET    /students/{student}                        students.show
PATCH  /students/{student}                        students.update
DELETE /students/{student}                        students.destroy
GET    /students/{student}/history                students.history

GET    /students/{student}/cards                  student-cards.index
POST   /students/{student}/cards                  student-cards.store
PATCH  /student-cards/{card}                      student-cards.update
POST   /student-cards/{card}/replace              student-cards.replace
POST   /student-cards/{card}/print                student-cards.print

resource /books, /authors, /categories, /locations, /copies
POST   /copies/labels/print                       copies.labels.print

GET    /visits                                    visits.index
POST   /visits/check-in                           visits.check-in
POST   /visits/{visit}/check-out                  visits.check-out
POST   /visits/{visit}/force-close                visits.force-close

POST   /visits/{visit}/consultations              consultations.open
GET    /consultations/{consultation}              consultations.show
POST   /consultations/{consultation}/items        consultation-items.store
POST   /consultations/{consultation}/returns      consultation-items.return
POST   /consultations/{consultation}/close        consultations.close
POST   /consultations/{consultation}/force-close  consultations.force-close

resource /loans (index, store, show)
POST   /loans/{loan}/items                        loan-items.store
POST   /loans/{loan}/returns                      loan-items.return
POST   /loans/{loan}/close                        loans.close

GET    /imports                                   imports.index
POST   /imports                                   imports.store
GET    /imports/{import}                          imports.show
POST   /imports/{import}/validate                 imports.validate
POST   /imports/{import}/commit                   imports.commit
POST   /imports/{import}/cancel                   imports.cancel

GET    /reports/attendance
GET    /reports/consultations
GET    /statistics
GET    /audit-logs
GET    /settings
PATCH  /settings
resource /users, /roles (administration)
```

L'espace étudiant réutilise des routes de lecture (`/me/visits`, `/me/consultations`, `/me/loans`, `/catalog`) dont le contrôleur dérive toujours l'étudiant de l'utilisateur connecté, jamais d'un ID fourni.

## 12. Écrans Vue/Inertia prévus

- `Dashboard/Index`: indicateurs adaptés au rôle, sessions ouvertes, retards, alertes.
- `Scan/Index`: grand champ autofocus, mode carte/exemplaire, retour instantané.
- `Visits/Desk`: pointage entrée/sortie, identité/photo, heure, livres actifs.
- `Consultations/Show`: session active, ajout scan, liste, restitution et clôture.
- `Students/Index|Create|Edit|Show|History`: recherche de secours, statut, historiques.
- `StudentCards/Index|Form|Print`: état, remplacement, aperçu impression.
- `Books/Index|Create|Edit|Show`, `Copies/Index|Create|Edit|Show`, écrans auteurs/catégories/emplacements.
- `Copies/Labels`: sélection, gabarit et impression QR/Code 128.
- `Visits/History`, `Consultations/History`, `Loans/Index|Show`: filtres et exports autorisés.
- `Statistics/Index`: fréquentation, durées, top ouvrages/exemplaires/catégories, niveau/parcours, pics, ouverts/non rendus.
- `Imports/Index|Upload|Review|Show`: mapping, erreurs ligne par ligne, validation et engagement.
- `Admin/Users`, `Admin/Roles`, `Admin/Settings`, `Admin/AuditLogs`.
- `Catalog/Index|Show` et `Me/*` pour l'étudiant.

Composants transversaux : `ScanInput`, `StudentIdentityCard`, `CopyStatusBadge`, `ActiveItemsTable`, `ForceCloseDialog`, `PermissionGuard`, filtres paginés et layout par rôle. Le serveur fournit toutes les autorisations effectives dans les props partagées ; masquer un bouton ne remplace jamais une Policy.

## 13. Services métier

- `NumberGenerator`: séquences transactionnelles.
- `CardLookupService` / `CopyLookupService`: résolution stricte et diagnostics de scan.
- `StudentService` / `CardService`: cycle de vie, unicité active, remplacement.
- `VisitService`: entrée, sortie, forçage et idempotence.
- `ConsultationService`: ouverture/clôture ; `ConsultationItemService`: ajout/retour.
- `LoanService`: création, ajout, retour, retard, clôture.
- `CatalogService`: ouvrage/exemplaires et transitions contrôlées.
- `BarcodeService`: valeur canonique, SVG/PDF, gabarits d'impression.
- `ImportService`, `WorkbookProfiler`, `RowNormalizer`, `ImportValidator`, `ImportCommitter`: pipeline Excel.
- `StatisticsService`: requêtes agrégées, plages et fuseau cohérents.
- `AuditService`: journal append-only dans la transaction de l'action.
- `SettingsService`: valeurs typées, cache et invalidation.

Les contrôleurs restent minces ; Form Requests valident la forme, Policies l'autorisation, services/actions les invariants et transactions.

## 14. Transactions nécessaires

- génération de chaque numéro + création de son objet ;
- activation/remplacement d'une carte (verrou étudiant/cartes) ;
- check-in et check-out ;
- ouverture/clôture/forçage d'une consultation ;
- ajout d'un exemplaire à une consultation et changement de statut ;
- restitution et changement de statut ;
- création d'un prêt, ajout/retour de chaque exemplaire, clôture ;
- forçages + audit obligatoirement atomiques ;
- engagement d'une ligne/lot d'import + ouvrage + N exemplaires + compteurs ;
- modification critique de paramètres/permissions + audit.

Ordre de verrouillage stable pour limiter les deadlocks : étudiant → visite → session/prêt → exemplaire → item → séquence. Retry borné sur deadlock. Les événements/notifications externes partent `afterCommit`.

## 15. Tests Pest à prévoir

### Unitaires

- formats/portées/dépassements de `NumberGenerator`, enums et transitions ;
- normalisation scan/ISBN/quantité/Unicode ;
- génération QR/Code 128 sans donnée personnelle ;
- mapping, validation et idempotence import ; calcul de durées/fuseaux statistiques.

### Feature/intégration

- authentification, chacun des trois rôles, chaque Policy et interdiction horizontale étudiant ;
- CRUD catalogue/cartes et unicités ; carte expirée/suspendue/remplacée ; étudiant inactif ; recherche de secours ;
- check-in, double scan, double présence concurrente, sortie normale/refus avec livres ;
- consultation multi-exemplaires, rescan idempotent, exemplaire prêté/occupé, retours, clôture ;
- prêt incompatible avec consultation et inversement ; retard et retour ;
- forçage superadmin : motif requis, audit créé, interdiction secrétaire ;
- import fichier invalide, fichier vide, feuilles multiples, cellules/formules, quantité ambiguë, N copies, deux titres identiques non fusionnés, reprise après échec ;
- exports/impressions et échappement des valeurs ; statistiques exactes par période locale ;
- concurrence via deux connexions/processus pour séquences et ressources actives ; contraintes DB testées directement.

### Front-end/E2E

- autofocus après navigation et après réponse, saisie lecteur USB + Entrée, double soumission, messages d'erreur ;
- affichage identité/heure/livres, restitution, impression ; responsive clavier-first et accessibilité.

La suite utilise une base MySQL de test dédiée, `RefreshDatabase`, factories/seeders déterministes et une horloge figée. SQLite seul ne suffit pas pour vérifier colonnes générées, verrous et concurrence MySQL.

## 16. Risques fonctionnels et techniques

| Risque | Réponse proposée |
|---|---|
| Fichiers Excel vides/inconnus | Obtenir les originaux et profiler avant mapping |
| PHP 8.3 au lieu de 8.4 | Aligner CLI/FPM/CI et Composer avant développement |
| Starter Inertia absent malgré la stack imposée | Installer/configurer sans réinstaller Laravel |
| Ambiguïté « scan carte ouverte = sortie » | Écran/mode explicite et confirmation selon contexte |
| Carte étudiant existante : format inconnu | Échantillons réels et compatibilité lecteur avant choix symbologie |
| Une seule carte/présence/session active sous concurrence | Colonnes générées uniques + verrou pessimiste |
| Perte/vol/détérioration lors d'un forçage | Motif + statut explicite + audit, jamais simple remise disponible |
| Soft delete et unicité | Interdire réutilisation des identifiants physiques ; politique d'archivage claire |
| Données personnelles/audit | Accès minimal, rétention, stockage privé, QR opaque, sauvegardes chiffrées |
| Volume des imports et statistiques | Lecture chunkée/jobs, index mesurés, agrégats/cache après profilage |
| Fuseau et changement de date | UTC en stockage, portée locale explicite, tests de minuit |
| Imprimantes/lecteurs hétérogènes | Page de calibration et essais sur le matériel EDSP |
| RBAC trop permissif | Permissions atomiques, deny-by-default, tests de matrice |
| Journal modifiable par admin DB | Append-only applicatif + sauvegarde/export ; définir exigences de non-répudiation |
| Données académiques non synchronisées | Décider source de vérité et procédure d'actualisation avant import étudiants |

Décisions fonctionnelles à valider : politique de durée/expiration des cartes, droits précis d'édition catalogue de la secrétaire, critères de prêt, durée/renouvellement, traitement d'une copie détériorée ou manquante, rétention des imports/audits/photos, format des niveaux/parcours/années, et comportement exact du second scan de carte.

## 17. Planning par phases

1. **Validation de conception** : arbitrer les décisions ci-dessus, récupérer/profiler classeurs et cartes, valider schéma/permissions/workflows.
2. **Socle technique** : PHP 8.4, Inertia/Vue/TS, auth, locale/fuseau, CI, base test, RBAC, layout et audit.
3. **Référentiels** : étudiants, cartes, auteurs, catégories, emplacements, ouvrages, exemplaires, séquences et codes imprimables.
4. **Pointage** : lookup carte, entrée/sortie, console secrétaire, contraintes concurrentes et audit.
5. **Consultation sur place** : sessions, scans exemplaires, retours, clôtures normales/forcées.
6. **Prêts** : politiques d'éligibilité, prêts/retours/retards/restrictions, incompatibilités de statut.
7. **Import Excel** : profilage réel, mapping, staging/revue, validation, engagement idempotent et rapports.
8. **Portail étudiant et rapports** : catalogue, historiques personnels, statistiques et exports.
9. **Durcissement/recette** : concurrence, sécurité, performance, sauvegarde/restauration, matériel de scan/impression, formation et déploiement.

Chaque phase se termine par tests Pest, revue d'autorisation, migration réversible, données de démonstration et recette métier. La prochaine action, après validation de ce rapport, est uniquement la phase 2 ; aucune migration métier ne doit précéder cette validation.
