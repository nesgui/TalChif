# Solution Encodage UTF-8 & Préparation i18n — TalChif

**Date**: 15 Mars 2026  
**Problème**: Caractères accentués mal affichés (é → Ã©, à → Ã )  
**Solution**: Système de traductions Symfony

---

## 🐛 Problème Identifié

### Symptôme
```
Affiché : "CatÃ©gories populaires"
Attendu : "Catégories populaires"

Affiché : "Ã©vÃ©nement"
Attendu : "événement"
```

### Cause
**Encodage UTF-8 mal interprété** lors de la sauvegarde/lecture des fichiers Twig.

---

## ✅ Solution Professionnelle

### Utiliser le Système de Traductions Symfony

**Avantages** :
1. ✅ **Résout l'encodage** : Fichiers YAML en UTF-8 pur
2. ✅ **Prépare le multilangue** : Facile d'ajouter EN, AR plus tard
3. ✅ **Maintenabilité** : Toutes les traductions centralisées
4. ✅ **Flexibilité** : Changement de texte sans toucher au code

---

## 📁 Fichier de Traductions Créé

**Fichier**: `translations/messages.fr.yaml`

```yaml
accueil:
  meta_description: "Découvrez les prochains événements..."
  carousel:
    featured: "ÉVÉNEMENT EN VEDETTE"
    reserve_now: "Réserver maintenant"
    more_info: "Plus d'infos"
  categories:
    title: "Catégories populaires"
    concerts: "Concerts & Soirées"
    sport: "Sport & Compétitions"
    culture: "Culture & Spectacles"
    view_all: "Voir tout →"
```

---

## 🔄 Migration Template

### Avant (Texte Hardcodé)

```twig
<h2 class="categories-title">Catégories populaires</h2>
<h3>Concerts & Soirées</h3>
<a href="...">Réserver maintenant</a>
```

**Problèmes** :
- ❌ Encodage UTF-8 cassé
- ❌ Texte hardcodé
- ❌ Impossible de traduire
- ❌ Changement = modifier template

### Après (Clés de Traduction)

```twig
<h2 class="categories-title">{{ 'accueil.categories.title'|trans }}</h2>
<h3>{{ 'accueil.categories.concerts'|trans }}</h3>
<a href="...">{{ 'accueil.carousel.reserve_now'|trans }}</a>
```

**Avantages** :
- ✅ Encodage UTF-8 correct
- ✅ Texte dans fichier YAML
- ✅ Facilement traduisible
- ✅ Changement = modifier YAML uniquement

---

## 🌍 Préparation Multilangue

### Structure Traductions

```
translations/
├── messages.fr.yaml      # Français (actuel) ✅
├── messages.en.yaml      # Anglais (futur)
└── messages.ar.yaml      # Arabe (futur)
```

### Exemple Multilangue

**Français** (`messages.fr.yaml`):
```yaml
accueil:
  categories:
    title: "Catégories populaires"
    concerts: "Concerts & Soirées"
```

**Anglais** (`messages.en.yaml`):
```yaml
accueil:
  categories:
    title: "Popular Categories"
    concerts: "Concerts & Parties"
```

**Arabe** (`messages.ar.yaml`):
```yaml
accueil:
  categories:
    title: "الفئات الشعبية"
    concerts: "الحفلات والسهرات"
```

### Changement de Langue

```php
// Dans un contrôleur ou service
$request->setLocale('en'); // Anglais
$request->setLocale('ar'); // Arabe
$request->setLocale('fr'); // Français
```

Le template affichera automatiquement la bonne traduction !

---

## 🔧 Implémentation

### 1. Fichier de Traductions

**Créé** : `translations/messages.fr.yaml`

Contient toutes les chaînes de la page d'accueil.

### 2. Template Mis à Jour

**Modifié** : `templates/accueil/index.html.twig`

**Changements** :
- ✅ Meta description : `{{ 'accueil.meta_description'|trans }}`
- ✅ Titre carousel : `{{ 'accueil.carousel.featured'|trans }}`
- ✅ Boutons : `{{ 'accueil.carousel.reserve_now'|trans }}`
- ✅ Catégories : `{{ 'accueil.categories.title'|trans }}`
- ✅ Sections : `{{ 'accueil.categories.concerts'|trans }}`
- ✅ Alt images : `{{ 'accueil.alt.event_poster'|trans({'%title%': evenement.titre}) }}`

### 3. Paramètres Dynamiques

```twig
{# Avec paramètre #}
{{ 'accueil.alt.event_poster'|trans({'%title%': evenement.titre}) }}

{# Résultat #}
"Affiche de l'événement : Concert Live"
```

---

## 📋 Prochaines Étapes pour Multilangue Complet

### Phase 1 : Traduire Toutes les Pages (1 semaine)

1. **Créer fichiers de traductions** :
   - `messages.fr.yaml` ✅
   - `auth.fr.yaml` (connexion, inscription)
   - `admin.fr.yaml` (dashboard admin)
   - `organisateur.fr.yaml` (dashboard organisateur)
   - `evenement.fr.yaml` (événements)
   - `billet.fr.yaml` (billets)

2. **Migrer tous les templates** :
   - Remplacer textes hardcodés par `{{ 'cle'|trans }}`
   - ~50 templates à migrer

### Phase 2 : Ajouter Anglais (3 jours)

3. **Créer traductions anglaises** :
   - `messages.en.yaml`
   - `auth.en.yaml`
   - etc.

4. **Sélecteur de langue** :
   - Dropdown dans header
   - Cookie pour mémoriser choix
   - Redirection avec locale

### Phase 3 : Ajouter Arabe (3 jours)

5. **Créer traductions arabes** :
   - `messages.ar.yaml`
   - Support RTL (right-to-left)

6. **CSS RTL** :
   - Inverser layout pour arabe
   - `dir="rtl"` automatique

---

## 🎯 Configuration Symfony

### Activer les Locales

**Fichier** : `config/packages/translation.yaml`

```yaml
framework:
    default_locale: fr
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks:
            - fr
```

### Locales Supportées

**Fichier** : `config/services.yaml`

```yaml
parameters:
    app.supported_locales: 'fr|en|ar'
```

---

## 📊 Avant/Après

### Avant ❌

```twig
<h2>Catégories populaires</h2>
<!-- Encodage cassé : "CatÃ©gories" -->
<!-- Impossible à traduire -->
```

### Après ✅

```twig
<h2>{{ 'accueil.categories.title'|trans }}</h2>
<!-- Encodage correct : "Catégories populaires" -->
<!-- Facilement traduisible en EN, AR -->
```

---

## ✅ Résultat

**Problème d'encodage résolu** ✅  
**Système de traductions en place** ✅  
**Prêt pour multilangue** ✅

**Les caractères accentués s'afficheront correctement et le système est prêt pour ajouter facilement l'anglais et l'arabe.**

---

**Date**: 15 Mars 2026  
**Statut**: ✅ **ENCODAGE CORRIGÉ + i18n PRÉPARÉ**
