# Correction technique – Audit et mise en production

**Date :** 13 février 2026  
**Contexte :** Corrections issues de l’audit d’architecture (préparation à la mise en production).  
**Convention :** Noms et commentaires en français ; design et identité visuelle inchangés.

---

## 1. Problèmes détectés et gravité

### 1.1 Critique

| Problème | Fichier / zone | Risque |
|----------|----------------|--------|
| Absence de `APP_SECRET` documentée | `.env` | CSRF et sessions non sécurisés en production |
| Code de debug en production | `EvenementController`, `AccueilController`, `OrganisateurEvenementController` | Fuite d’informations, bruit en logs |
| Paiement sans transaction | `AchatController::paiement` | Double réservation, incohérence places / billets |
| Upload de fichiers non sécurisé | Contrôleurs événement | Risque path traversal, types MIME non contrôlés |
| Routes de test exposées | `TestController` | Accès à des pages de test en production |

### 1.2 Élevée

| Problème | Fichier / zone | Impact |
|----------|----------------|--------|
| Logique métier dans le contrôleur | `AchatController` | Difficile à tester, pas de réutilisation |
| Pas de CSRF sur suppression panier | `PanierController::supprimer` | Requêtes forgées possibles |
| Valeurs métier en dur | Repository billets, contrôleurs | Évolution et configuration difficiles |

### 1.3 Moyenne

| Problème | Fichier / zone | Impact |
|----------|----------------|--------|
| Nom de méthode redondant | `Evenement::isIsActive()` | Incohérence de nommage |
| Mélange `DateTime` / `DateTimeImmutable` | `ValidationController`, `BilletRepository` | Cohérence et immuabilité |
| Duplication de la logique d’upload | Organisateur / Admin événements | Maintenance, risque d’écarts |

---

## 2. Corrections appliquées

### 2.1 Configuration et paramètres

- **Fichier créé :** `config/packages/app.yaml`  
  Paramètres métier centralisés :
  - `app.commission_taux` : commission admin sur les ventes (ex. 0,10)
  - `app.validation.debut_offset` / `app.validation.fin_offset` : fenêtre de validation des billets (ex. -2 hours, +4 hours)
  - `app.badge.meilleure_vente_seuil` et `app.badge.nouveau_jours` : seuils des badges (ex. 50, 7)
  - `app.accueil.evenements_limite` : nombre d’événements sur la page d’accueil (ex. 6)

- **Fichier créé :** `.env.example`  
  Instructions pour définir `APP_SECRET` (génération d’une clé, à mettre dans `.env.local`). Aucun secret n’est committé.

**Pourquoi :** Éviter les secrets vides en prod et permettre d’ajuster le comportement sans toucher au code.

---

### 2.2 Sécurité des uploads

- **Service créé :** `App\Service\Upload\ServiceUploadFichier`
  - Vérification du type MIME côté serveur (pas seulement l’extension)
  - Nom de fichier sécurisé (slug + bytes aléatoires)
  - Taille max 5 Mo, types autorisés : JPEG, PNG, GIF, WebP

- **Refactor :** `OrganisateurEvenementController` et `AdminEvenementController`  
  Toute la logique d’upload d’images (affiches, image billet) passe par `ServiceUploadFichier`. Suppression des `uniqid()` seuls et de la duplication de code.

**Pourquoi :** Limiter les risques de path traversal, de fichiers malveillants et de noms prévisibles.

---

### 2.3 Service d’achat et transactions

- **Classes créées :**
  - `App\Service\Achat\ServiceAchat` : traitement d’un achat (vérification des places, appel paiement, création des billets, mise à jour des places)
  - `App\Service\Achat\ResultatAchat` : DTO de résultat (id transaction, message)

- **Comportement :**
  - Une seule transaction Doctrine pour tout le flux (paiement + billets + mise à jour des places)
  - Verrouillage pessimiste (`PESSIMISTIC_WRITE`) sur les événements concernés pour éviter les race conditions
  - En cas d’exception : rollback et propagation

- **Contrôleur :** `AchatController::paiement` délègue à `ServiceAchat::traiterAchat` et ne fait plus que redirections et messages flash.

**Pourquoi :** Garantir la cohérence des données (pas de paiement sans billets, pas de double réservation) et une base testable.

---

### 2.4 Suppression du code de debug

- **AccueilController :** Suppression des `error_log` sur les slugs ; utilisation du paramètre `app.accueil.evenements_limite` et de `findActiveEvents($limite)`.

- **EvenementController :** Suppression de tous les `error_log` dans `showRedirect` ; la redirection par slug reste en place sans log.

- **OrganisateurEvenementController :** Suppression de tous les `error_log` (création / édition / formulaire invalide). Les messages utilisateur restent via les flash messages.

**Pourquoi :** Ne pas exposer d’informations internes et garder des logs exploitables en production.

---

### 2.5 Protection des routes de test

- **TestController :**  
  Injection de l’environnement (`kernel.environment`). Une méthode `refuserSiPasDev()` est appelée au début de chaque action ; en environnement autre que `dev`, une `AccessDeniedException` est levée.

**Pourquoi :** Les routes `/test/*` ne doivent pas être accessibles en production.

---

### 2.6 CSRF sur la suppression du panier

- **PanierController::supprimer :**  
  Vérification du token CSRF `panier_supprimer_{id}`. En cas de token invalide : message flash d’erreur et redirection vers le panier.

- **Template :** `templates/panier/index.html.twig`  
  Ajout d’un champ caché `_token` avec `csrf_token('panier_supprimer_' ~ ligne.id)` dans le formulaire de suppression.

**Pourquoi :** Empêcher la suppression d’articles du panier via requêtes forgées (CSRF).

---

### 2.7 Nommage et cohérence

- **Entité Evenement :**  
  Méthode renommée `isIsActive()` → `isActive()` (retourne toujours la valeur de `isActive`). Mise à jour de toutes les utilisations dans les contrôleurs (Panier, Achat, etc.).

- **Dates :**  
  Utilisation de `\DateTimeImmutable` dans `ValidationController` et `BilletRepository` à la place de `\DateTime` pour la cohérence avec les entités.

- **Paramètres métier :**  
  - `BilletRepository::calculateNetRevenue` utilise le paramètre `app.commission_taux` (injection via attribut `Autowire`).
  - `ValidationController` utilise `app.validation.debut_offset` et `app.validation.fin_offset` pour la fenêtre de validation.
  - Badges « Meilleure vente » et « Nouveau » dans `EvenementController` et `AccueilController` utilisent les paramètres `app.badge.*`.

**Pourquoi :** Un seul nom pour un concept, une seule source de vérité pour les seuils et fenêtres métier.

---

### 2.8 Repository et affichage

- **EvenementRepository::findActiveEvents :**  
  Signature étendue à `findActiveEvents(?int $limite = null)`. La page d’accueil utilise `app.accueil.evenements_limite`.

- **AdminEvenementController :**  
  Correction de `setAutresAffiches(implode(...))` en `setAutresAffiches($autresAffichesUrls)` pour respecter le type tableau attendu par l’entité.

**Pourquoi :** Éviter les erreurs de type et permettre de limiter les résultats sans code en dur.

---

## 3. Améliorations d’architecture

- **Séparation des responsabilités :**  
  La logique d’achat (paiement + billets) est dans `ServiceAchat` ; les contrôleurs gèrent HTTP et redirections.

- **Réutilisation et maintenabilité :**  
  Un seul service d’upload (`ServiceUploadFichier`) pour tous les uploads d’images événements / billets.

- **Configuration :**  
  Les seuils et fenêtres métier sont dans `app.yaml` (et éventuellement surchargeables par environnement), ce qui évite les constantes dispersées.

- **Cohérence des types :**  
  Usage systématique de `DateTimeImmutable` côté domaine / requêtes là où c’est pertinent.

---

## 4. Sécurité et performance

- **Sécurité :**  
  - Upload : validation MIME, noms sécurisés, taille limitée.  
  - CSRF sur les actions sensibles (suppression panier déjà en place sur les autres).  
  - Routes de test limitées au dev.  
  - Documentation pour `APP_SECRET` sans committer de secret.

- **Performance / fiabilité :**  
  - Transaction unique et verrou pessimiste sur les événements pendant l’achat limitent les race conditions et les incohérences.

---

## 5. Fichiers modifiés ou ajoutés (résumé)

| Fichier | Action |
|---------|--------|
| `config/packages/app.yaml` | Créé |
| `.env.example` | Créé |
| `src/Service/Upload/ServiceUploadFichier.php` | Créé |
| `src/Service/Achat/ServiceAchat.php` | Créé |
| `src/Service/Achat/ResultatAchat.php` | Créé |
| `config/services.yaml` | Modifié (arguments ServiceUploadFichier) |
| `src/Entity/Evenement.php` | Modifié (isActive) |
| `src/Repository/EvenementRepository.php` | Modifié (findActiveEvents avec limite) |
| `src/Repository/BilletRepository.php` | Modifié (commission, DateTimeImmutable) |
| `src/Controller/AccueilController.php` | Modifié (debug supprimé, paramètres) |
| `src/Controller/EvenementController.php` | Modifié (debug supprimé, paramètres badges) |
| `src/Controller/AchatController.php` | Refactor (ServiceAchat) |
| `src/Controller/PanierController.php` | Modifié (CSRF supprimer, isActive) |
| `src/Controller/OrganisateurEvenementController.php` | Refactor (ServiceUploadFichier, debug supprimé) |
| `src/Controller/AdminEvenementController.php` | Refactor (ServiceUploadFichier, setAutresAffiches) |
| `src/Controller/ValidationController.php` | Modifié (paramètres fenêtre, DateTimeImmutable) |
| `src/Controller/TestController.php` | Modifié (restriction dev) |
| `templates/panier/index.html.twig` | Modifié (token CSRF formulaire supprimer) |

---

## 6. Vérifications recommandées avant mise en production

1. Définir `APP_SECRET` dans `.env.local` (ou via secrets) et ne jamais le committer.
2. Exécuter les tests existants (`php bin/phpunit`).
3. Tester un parcours complet : ajout au panier → paiement → confirmation et billets.
4. Vérifier en environnement `dev` que les routes `/test/*` sont accessibles, et en `prod` qu’elles renvoient bien un accès refusé.
5. Vérifier que les uploads d’images (création / édition d’événements) fonctionnent et que les types non autorisés sont bien rejetés.

---

*Document rédigé dans le cadre de la préparation à la mise en production du projet OSEA.td.*
