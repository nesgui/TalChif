# Documentation - Gestion des Erreurs

## Vue d'ensemble

Ce document décrit le système de gestion des erreurs implémenté dans le projet TalChif pour les formulaires, menus et boutons.

## Architecture

### 1. Service Centralisé (ErrorHandlingService)

**Fichier**: `src/Service/ErrorHandlingService.php`

Le service `ErrorHandlingService` fournit une gestion unifiée des erreurs dans toute l'application.

#### Fonctionnalités principales:
- Gestion des messages flash (erreur, succès, avertissement)
- Validation et nettoyage des données de requête
- Gestion des erreurs de formulaire
- Gestion des erreurs de sécurité, base de données, upload
- Journalisation des erreurs

#### Utilisation dans les contrôleurs:

```php
use App\Service\ErrorHandlingService;

class MonController extends AbstractController
{
    public function __construct(
        private ErrorHandlingService $errorHandling
    ) {
    }

    public function maMethode(Request $request): Response
    {
        try {
            // Logique métier
            $this->errorHandling->addSuccessFlash('Opération réussie');
        } catch (\Throwable $e) {
            $this->errorHandling->handleDatabaseError($e);
            $this->errorHandling->logError($e, ['action' => 'mon_action']);
        }
    }
}
```

### 2. Templates Twig

**Fichier**: `templates/partials/form_errors.html.twig`

Templates réutilisables pour l'affichage des erreurs:

- `form_errors`: Affiche les erreurs globales d'un formulaire
- `field_error`: Affiche les erreurs d'un champ spécifique
- `form_group`: Groupe de formulaire avec gestion d'erreurs intégrée
- `form_button`: Bouton avec état de chargement
- `flash_alerts**: Alertes pour les messages flash

#### Utilisation dans les templates:

```twig
{# Inclure les alertes flash #}
{% include 'partials/flash_alerts.html.twig' %}

{# Afficher les erreurs d'un formulaire #}
{% include 'partials/form_errors.html.twig' with {'form': form} %}

{# Afficher les erreurs d'un champ #}
{% include 'partials/field_error.html.twig' with {'field': form.nom} %}

{# Groupe de formulaire complet #}
{% include 'partials/form_group.html.twig' with {
    'form': form.nom,
    'label': 'Nom',
    'placeholder': 'Entrez votre nom'
} %}
```

### 3. JavaScript Client

**Fichier**: `assets/js/menu-error-handler.js`

Gestion des erreurs côté client pour les interactions utilisateur:

#### Fonctionnalités:
- Validation des clics sur les menus
- Gestion des états de chargement
- Interception des erreurs AJAX
- Validation des formulaires
- Gestion des erreurs réseau

#### Événements gérés:
- `click`: Sur les liens de menu et boutons
- `submit`: Sur les formulaires
- `turbo:before-fetch-request`: Chargement Turbo
- `turbo:fetch-request-error`: Erreurs Turbo
- `unhandledrejection`: Erreurs JavaScript globales

### 4. Styles CSS

**Fichier**: `assets/css/error-handling.css`

Styles cohérents pour tous les états d'erreur:

#### Classes principales:
- `.alert`: Alertes de message
- `.form-group.has-error`: Groupes de formulaire avec erreurs
- `.field-error-message`: Messages d'erreur de champ
- `.btn.loading`: Boutons en cours de chargement
- `.btn.error`: Boutons en erreur
- `.dashboard-link.error`: Liens de menu en erreur

## Types d'Erreurs Gérées

### 1. Erreurs de Formulaire

**Validation côté serveur:**
- Contraintes Symfony (NotBlank, Length, Regex, etc.)
- Validation personnalisée
- Messages d'erreur traduits

**Validation côté client:**
- Validation HTML5 native
- Validation personnalisée JavaScript
- Feedback visuel immédiat

### 2. Erreurs de Sécurité

- Accès refusé (403)
- Token CSRF invalide
- Permissions insuffisantes

### 3. Erreurs de Base de Données

- Violation de contrainte unique
- Erreur de connexion
- Erreurs de transaction

### 4. Erreurs d'Upload

- Fichier trop volumineux
- Type MIME invalide
- Erreur d'écriture

### 5. Erreurs Réseau

- Perte de connexion
- Timeout de requête
- Erreurs serveur

## Configuration

### Service Symfony

**Fichier**: `config/services/error_handling.yaml`

```yaml
services:
    App\Service\ErrorHandlingService:
        arguments:
            $session: '@session'
            $translator: '@translator' # Optionnel
```

### Importation des Assets

Dans `templates/layout/dashboard.html.twig`:
```twig
{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('css/error-handling.css') }}">
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    <script src="{{ asset('js/menu-error-handler.js') }}" defer></script>
{% endblock %}
```

## Bonnes Pratiques

### 1. Dans les Contrôleurs

```php
// Toujours utiliser le service pour les messages
$this->errorHandling->addErrorFlash('Message d\'erreur');

// Gérer les exceptions avec try-catch
try {
    // Logique métier
} catch (DatabaseException $e) {
    $this->errorHandling->handleDatabaseError($e);
} catch (SecurityException $e) {
    $this->errorHandling->handleSecurityError($e);
}

// Valider les formulaires soumis
if ($form->isSubmitted()) {
    if (!$form->isValid()) {
        $this->errorHandling->handleFormErrors($form);
    }
}
```

### 2. Dans les Templates

```twig
{# Toujours inclure les alertes flash #}
{% include 'partials/flash_alerts.html.twig' %}

{# Utiliser les templates réutilisables #}
{% include 'partials/form_group.html.twig' with {
    'form': form.champ,
    'label': 'Label du champ',
    'help': 'Texte d\'aide optionnel'
} %}

{# Ajouter des classes CSS pour le feedback visuel #}
<div class="form-group {% if form.champ.vars.errors|length > 0 %}has-error{% endif %}">
```

### 3. En JavaScript

```javascript
// Le gestionnaire d'erreurs est automatiquement initialisé
// Pour des actions personnalisées:
window.menuErrorHandler.showButtonError(button, 'Message d\'erreur');
window.menuErrorHandler.showMenuError(link, 'Message d\'erreur');
```

## Messages d'Erreur

### Messages Standards

Le système utilise des messages standards qui peuvent être traduits:

- `error.access_denied`: Accès refusé
- `error.invalid_csrf_token`: Token CSRF invalide
- `error.duplicate_entry`: Entrée en double
- `error.database_connection`: Erreur de connexion BDD
- `error.file_too_large`: Fichier trop volumineux
- `error.file_invalid_type`: Type de fichier invalide

### Messages Personnalisés

```php
// Message simple
$this->errorHandling->addErrorFlash('Mon message personnalisé');

// Message avec paramètres
$this->errorHandling->addErrorFlash('Erreur pour {{ field }}', [
    'field' => $fieldName
]);
```

## Tests et Validation

### 1. Tests Unitaires

Tester le service `ErrorHandlingService`:
- Validation des données
- Gestion des messages flash
- Gestion des différents types d'erreurs

### 2. Tests Fonctionnels

Tester les scénarios d'erreur:
- Soumission de formulaire invalide
- Actions non autorisées
- Erreurs réseau
- Upload de fichiers invalides

### 3. Tests Manuels

Vérifier:
- L'affichage correct des messages d'erreur
- Les états de chargement
- La cohérence visuelle
- L'accessibilité

## Dépannage

### Problèmes Communs

1. **Messages non affichés**: Vérifier l'inclusion des templates flash
2. **Styles non appliqués**: Vérifier l'importation du CSS
3. **JavaScript non fonctionnel**: Vérifier le chargement du script
4. **Service non injecté**: Vérifier la configuration des services

### Débogage

```php
// Activer le débogage des erreurs
$this->errorHandling->logError($exception, [
    'controller' => static::class,
    'method' => __METHOD__,
    'user_id' => $this->getUser()?->getId()
]);
```

## Évolution Future

### Améliorations Possibles

1. **Internationalisation complète**: Traduction de tous les messages
2. **Logging avancé**: Intégration avec Monolog/Logstash
3. **Monitoring**: Métriques d'erreurs en temps réel
4. **Tests automatisés**: Suite de tests complète
5. **Accessibilité**: Amélioration pour lecteurs d'écran

### Extensions

1. **Notifications push**: Pour les erreurs critiques
2. **Rapports d'erreurs**: Export et analyse
3. **Intégration Sentry**: Monitoring d'erreurs externe
4. **Mode maintenance**: Gestion des périodes de maintenance

---

Cette documentation doit être mise à jour régulièrement lors de modifications du système de gestion des erreurs.
