# Guide d'Utilisation — Système Typographie Responsive

**Date**: 13 Mars 2026  
**Statut**: ✅ Système implémenté et opérationnel

---

## 🎯 Vue d'Ensemble

Le système de typographie responsive de TalChif utilise **CSS `clamp()`** pour un scaling automatique sur **4 catégories d'écrans** :
- 📱 Smartphone (320px - 767px)
- 📱 Tablette (768px - 1023px)  
- 💻 Desktop (1024px - 1919px)
- 📺 TV/Large (1920px+)

---

## 📚 Variables Disponibles

### Niveau 1 : Display (Héros)

```css
--display-xl   /* 32px → 72px */
--display-l    /* 28px → 64px */
--display-m    /* 24px → 56px */
```

**Usage** : Landing pages, héros principaux

### Niveau 2 : Headings (Titres)

```css
--h1   /* 24px → 40px */
--h2   /* 20px → 36px */
--h3   /* 18px → 32px */
--h4   /* 16px → 24px */
--h5   /* 14px → 20px */
--h6   /* 14px → 18px */
```

**Usage** : Titres de pages, sections, cards

### Niveau 3 : Body Text

```css
--text-lead     /* 18px → 28px */
--text-lg       /* 16px → 22px */
--text-base     /* 15px → 20px */
--text-sm       /* 14px → 18px */
--text-caption  /* 13px → 16px */
--text-tiny     /* 12px → 14px */
```

**Usage** : Paragraphes, descriptions, méta-informations

### Niveau 4 : UI Elements

```css
--ui-btn-lg    /* 16px → 22px */
--ui-btn       /* 15px → 20px */
--ui-btn-sm    /* 14px → 18px */
--ui-label     /* 14px → 18px */
--ui-input     /* 15px → 20px */
```

**Usage** : Boutons, labels, inputs

### Niveau 5 : Cards & Components

```css
--card-title    /* 16px → 22px */
--card-meta     /* 13px → 16px */
--card-body     /* 14px → 18px */
--badge         /* 11px → 14px */
--stat-number   /* 24px → 40px */
--stat-label    /* 13px → 16px */
```

**Usage** : Cards événements, statistiques, badges

### Niveau 6 : Navigation

```css
--nav-link   /* 14px → 18px */
--nav-label  /* 11px → 14px */
--menu-item  /* 15px → 18px */
```

**Usage** : Bottom nav, menu drawer, liens navigation

---

## 💡 Exemples d'Utilisation

### Page de Connexion

```html
<h1 class="auth-title">Connexion</h1>
<!-- Utilise var(--h1) : 24px mobile → 40px TV -->

<p class="auth-subtitle">Accédez à votre espace</p>
<!-- Utilise var(--text-base) : 15px mobile → 20px TV -->

<label class="auth-label">Email *</label>
<!-- Utilise var(--ui-label) : 14px mobile → 18px TV -->

<input class="auth-input" type="email">
<!-- Utilise var(--ui-input) : 15px mobile → 20px TV -->

<button class="btn">Se connecter</button>
<!-- Utilise var(--ui-btn) : 15px mobile → 20px TV -->
```

### Card Événement

```html
<h3 class="event-title">Concert Live</h3>
<!-- Utilise var(--card-title) : 16px mobile → 22px TV -->

<p class="card-meta">N'Djamena • 15 Mars</p>
<!-- Utilise var(--card-meta) : 13px mobile → 16px TV -->

<span class="badge">Nouveau</span>
<!-- Utilise var(--badge) : 11px mobile → 14px TV -->

<div class="product-price">5 000 XAF</div>
<!-- Utilise var(--text-lg) : 16px mobile → 22px TV -->
```

### Dashboard Stats

```html
<div class="stat-number">1,234</div>
<!-- Utilise var(--stat-number) : 24px mobile → 40px TV -->

<div class="stat-label">Billets vendus</div>
<!-- Utilise var(--stat-label) : 13px mobile → 16px TV -->
```

### Hero Landing

```html
<h1 class="hero-title">Découvrez les événements</h1>
<!-- Utilise var(--display-m) : 24px mobile → 56px TV -->

<h2 class="section-title">Événements à venir</h2>
<!-- Utilise var(--h3) : 18px mobile → 32px TV -->
```

---

## 🎨 Classes Utilitaires

### Font Weight

```css
.fw-light      /* 300 */
.fw-normal     /* 400 */
.fw-medium     /* 500 */
.fw-semibold   /* 600 */
.fw-bold       /* 700 */
.fw-extrabold  /* 800 */
```

### Line Height

```css
.lh-tight    /* 1.2 - Titres */
.lh-snug     /* 1.4 - Sous-titres */
.lh-normal   /* 1.6 - Corps de texte */
.lh-relaxed  /* 1.8 - Texte aéré */
.lh-loose    /* 2.0 - Très aéré */
```

### Letter Spacing

```css
.ls-tighter  /* -0.05em - Grands titres */
.ls-tight    /* -0.025em - H1, H2 */
.ls-normal   /* 0 - Standard */
.ls-wide     /* 0.025em - Labels, boutons */
.ls-wider    /* 0.05em - Badges */
.ls-widest   /* 0.1em - Majuscules */
```

---

## 📐 Tableau de Référence Rapide

| Élément | Variable | Mobile | Tablet | Desktop | TV |
|---------|----------|--------|--------|---------|-----|
| **Hero principal** | `--display-xl` | 32px | 40px | 56px | 72px |
| **Titre page (H1)** | `--h1` | 24px | 28px | 32px | 40px |
| **Titre section (H2)** | `--h2` | 20px | 24px | 28px | 36px |
| **Titre card** | `--card-title` | 16px | 17px | 18px | 22px |
| **Corps texte** | `--text-base` | 15px | 16px | 16px | 20px |
| **Bouton** | `--ui-btn` | 15px | 16px | 16px | 20px |
| **Label** | `--ui-label` | 14px | 15px | 15px | 18px |
| **Badge** | `--badge` | 11px | 12px | 12px | 14px |
| **Stat nombre** | `--stat-number` | 24px | 28px | 32px | 40px |

---

## ✅ Éléments Mis à Jour

### Classes Principales

- ✅ `h1, h2, h3, h4, h5, h6` — Titres responsive
- ✅ `.page-title` — Titre de page
- ✅ `.page-subtitle` — Sous-titre de page
- ✅ `.card-title` — Titre de card
- ✅ `.card-meta` — Méta de card
- ✅ `.event-title` — Titre événement
- ✅ `.stat-number` — Chiffres statistiques
- ✅ `.stat-label` — Labels statistiques
- ✅ `.btn, .btn-sm, .btn-lg` — Boutons
- ✅ `.auth-label` — Labels formulaires
- ✅ `.auth-input` — Inputs formulaires
- ✅ `.badge` — Badges et tags
- ✅ `.public-tab-label` — Labels bottom nav
- ✅ `.hero-title` — Titre hero
- ✅ `.section-title` — Titre section
- ✅ `.product-title` — Titre produit
- ✅ `.product-price` — Prix
- ✅ `.form-help` — Aide formulaire
- ✅ `.pagination-info` — Info pagination

---

## 🔧 Bonnes Pratiques

### 1. Utiliser les Variables

**❌ Mauvais** :
```css
.mon-titre {
    font-size: 20px; /* Hardcodé, pas responsive */
}
```

**✅ Bon** :
```css
.mon-titre {
    font-size: var(--h2); /* Responsive automatique */
    font-weight: var(--fw-semibold);
    line-height: var(--lh-snug);
}
```

### 2. Choisir la Bonne Variable

**Pour un titre de page** : `var(--h1)` ou `var(--h2)`  
**Pour un titre de card** : `var(--card-title)` ou `var(--h4)`  
**Pour du texte standard** : `var(--text-base)`  
**Pour des méta-infos** : `var(--card-meta)` ou `var(--text-caption)`  
**Pour des badges** : `var(--badge)`

### 3. Combiner avec Font Weight

```css
.titre-important {
    font-size: var(--h2);
    font-weight: var(--fw-bold);
    line-height: var(--lh-tight);
}
```

### 4. Tester sur Tous les Écrans

Utiliser Chrome DevTools :
- Mobile : 375px, 414px
- Tablet : 768px, 1024px
- Desktop : 1440px, 1920px
- TV : 2560px, 3840px

---

## 📊 Avant/Après

### Avant (Système Fixe)

```css
.hero-title {
    font-size: 22px; /* Même taille partout */
}
```

**Rendu** :
- Mobile 375px : 22px (trop grand)
- Desktop 1920px : 22px (trop petit)

### Après (Système Responsive)

```css
.hero-title {
    font-size: var(--display-m); /* Scaling automatique */
}
```

**Rendu** :
- Mobile 375px : 24px (optimal)
- Tablet 768px : 32px (optimal)
- Desktop 1440px : 48px (optimal)
- TV 1920px : 56px (optimal)

---

## 🎯 Résultat

**Le système de typographie responsive est maintenant actif sur toute l'application.**

Toutes les tailles de police s'adaptent automatiquement à la taille de l'écran, garantissant :
- ✅ Lisibilité optimale sur smartphone
- ✅ Confort visuel sur tablette
- ✅ Clarté sur desktop
- ✅ Visibilité à distance sur TV

**Date**: 13 Mars 2026  
**Statut**: ✅ **SYSTÈME TYPOGRAPHIE RESPONSIVE OPÉRATIONNEL**
