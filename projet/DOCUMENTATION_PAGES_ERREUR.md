# Documentation - Pages d'erreur personnalisées TalChif

## Vue d'ensemble

Système complet de pages d'erreur personnalisées pour Symfony avec design épuré et professionnel, adapté à l'identité visuelle de TalChif.

## Pages d'erreur disponibles

### 1. **error.html.twig** (Erreur générique)
- **Utilisation** : Toutes les erreurs non spécifiquement gérées
- **Design** : Icône d'alerte, message générique, 2 boutons d'action
- **Couleur** : Rouge (erreur)

### 2. **error404.html.twig** (Page non trouvée)
- **Utilisation** : URL inexistante ou ressource supprimée
- **Design** : Code 404 en filigrane, icône triangle d'alerte, suggestions utiles
- **Couleur** : Orange (warning)
- **Fonctionnalités** :
  - Liste de suggestions avec icônes
  - Liens vers accueil et événements
  - Recherche recommandée

### 3. **error403.html.twig** (Accès refusé)
- **Utilisation** : Permissions insuffisantes
- **Design** : Icône cadenas, message explicatif, bouton connexion
- **Couleur** : Orange (warning)
- **Fonctionnalités** :
  - Explication claire des permissions
  - Lien vers connexion
  - Retour accueil

### 4. **error500.html.twig** (Erreur serveur)
- **Utilisation** : Erreur interne du serveur
- **Design** : Code 500 en filigrane, icône alerte, message rassurant
- **Couleur** : Rouge (error)
- **Fonctionnalités** :
  - Message rassurant (équipe notifiée)
  - Bouton "Réessayer" (reload)
  - Retour accueil

### 5. **error503.html.twig** (Service indisponible)
- **Utilisation** : Maintenance ou surcharge serveur
- **Design** : Icône horloge, message maintenance
- **Couleur** : Orange (warning)
- **Fonctionnalités** :
  - Message maintenance explicite
  - Bouton "Réessayer" prioritaire
  - Retour accueil secondaire

## Caractéristiques communes

### Design épuré et moderne
- Layout centré verticalement et horizontalement
- Typographie fluide avec `clamp()` pour responsive
- Icônes SVG inline (pas de dépendance externe)
- Code d'erreur en filigrane (opacité 15%)
- Palette de couleurs cohérente avec TalChif

### Responsive
- Tailles fluides via `clamp()`
- Padding/marges adaptatives
- Boutons empilés sur mobile
- Icônes redimensionnables

### Accessibilité
- Attribut `lang="fr"` sur `<html>`
- Textes clairs et explicatifs
- Contraste WCAG AA respecté
- Navigation au clavier possible

### Performance
- CSS inline (pas de requête externe)
- Icônes SVG (pas d'images)
- Pas de JavaScript (sauf bouton reload optionnel)
- Chargement instantané

## Emplacement des fichiers

```
templates/
└── bundles/
    └── TwigBundle/
        └── Exception/
            ├── error.html.twig       # Erreur générique
            ├── error403.html.twig    # Accès refusé
            ├── error404.html.twig    # Page non trouvée
            ├── error500.html.twig    # Erreur serveur
            └── error503.html.twig    # Service indisponible
```

## Configuration Symfony

### Automatique
Symfony détecte automatiquement les templates dans `templates/bundles/TwigBundle/Exception/` :
- `error{code}.html.twig` pour un code HTTP spécifique
- `error.html.twig` comme fallback

### Environnements
- **dev** : Affiche la page d'erreur détaillée de Symfony (profiler)
- **prod** : Affiche les templates personnalisés

### Tester en dev
Pour voir les pages d'erreur personnalisées en environnement dev :

```bash
# Modifier config/packages/framework.yaml
framework:
    error_controller: error_controller::preview

# Puis accéder à :
# http://localhost/_error/404
# http://localhost/_error/403
# http://localhost/_error/500
# http://localhost/_error/503
```

## Variables CSS utilisées

Les pages utilisent les variables CSS définies dans `assets/styles/app.css` :

```css
--couleur-fond          /* Fond principal */
--couleur-texte         /* Texte principal */
--couleur-texte-secondaire  /* Texte secondaire */
--couleur-surface       /* Fond des cartes */
--couleur-bordure       /* Bordures */
--couleur-primaire      /* Liens et accents */
--couleur-error         /* Icônes erreur (500) */
--couleur-warning       /* Icônes warning (404, 403, 503) */
--couleur-info          /* Icônes info */
--espace-*              /* Espacements */
--rayon                 /* Border radius */
```

## Personnalisation

### Modifier les messages
Éditer directement les fichiers `.html.twig` dans `templates/bundles/TwigBundle/Exception/`

### Modifier les couleurs
Les couleurs s'adaptent automatiquement au thème (clair/sombre) via les variables CSS

### Ajouter une nouvelle page d'erreur
Créer `error{code}.html.twig` en suivant le même pattern :

```twig
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Titre - TalChif</title>
    <link rel="icon" href="...">
    <link rel="stylesheet" href="/styles/app.css">
</head>
<body>
    <div class="error-page-container">
        <div class="error-page-content">
            <!-- Contenu -->
        </div>
    </div>
    <style>
        /* Styles inline */
    </style>
</body>
</html>
```

## Bonnes pratiques

### Messages
- ✅ Rassurants et professionnels
- ✅ Explications claires
- ✅ Actions concrètes proposées
- ❌ Jargon technique
- ❌ Messages d'erreur bruts

### Design
- ✅ Cohérent avec l'identité TalChif
- ✅ Responsive et accessible
- ✅ Chargement rapide
- ❌ Dépendances externes
- ❌ JavaScript complexe

### Navigation
- ✅ Toujours proposer un retour accueil
- ✅ Suggérer des alternatives pertinentes
- ✅ Boutons clairs et visibles
- ❌ Impasses (pas de sortie)
- ❌ Trop d'options (confusion)

## Support des thèmes

Les pages d'erreur supportent automatiquement le thème clair/sombre via les variables CSS de `app.css`. Le thème est détecté au chargement de la page.

## Maintenance

### Vérifier le bon fonctionnement
```bash
# En dev (avec error_controller activé)
curl http://localhost/_error/404
curl http://localhost/_error/500

# En prod (déclencher une vraie erreur)
# Accéder à une URL inexistante pour 404
# Lever une exception dans un controller pour 500
```

### Mise à jour
Lors de modifications de `app.css`, vérifier que les variables utilisées dans les pages d'erreur sont toujours valides.

---

**Date de création** : Mars 2026  
**Version** : 1.0  
**Auteur** : Équipe TalChif
