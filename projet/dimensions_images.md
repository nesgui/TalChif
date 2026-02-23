# Dimensions recommandées pour les images — TalChif

> Guide pour les organisateurs et administrateurs.

---

## Affiches événements (accueil + catalogue)

| Élément | Ratio | Dimensions recommandées | Format | Poids max |
|---------|-------|------------------------|--------|-----------|
| **Affiche principale** | 16:9 | **1280 × 720 px** (minimum 640 × 360) | JPEG, PNG, WebP | 5 Mo |
| **Autres affiches** | 16:9 | **1280 × 720 px** | JPEG, PNG, WebP | 5 Mo / image |

### Pourquoi 16:9 ?
- C'est le ratio utilisé sur les cartes événement de la page d'accueil (`aspect-ratio: 16/9`)
- S'adapte bien au mobile (pas trop haut, pas trop large)
- Compatible avec les écrans modernes sans recadrage

### Conseils
- Privilégier le **JPEG** pour les photos (meilleur rapport qualité/poids)
- Utiliser le **PNG** uniquement pour les visuels graphiques avec transparence
- Centrer les éléments importants (texte, logo) au milieu de l'image pour éviter le crop sur mobile
- Résolution minimale : **640 × 360 px** pour un affichage net sur les cartes

---

## Cartes événements sur la page d'accueil

| Zone d'affichage | Taille affichée | Comportement |
|-------------------|-----------------|--------------|
| **Carte catalogue** (grille accueil) | ~240–400 px de large | `object-fit: cover`, ratio 16:9 |
| **Carte héros** (bannière) | max 360 px de large | `object-fit: cover`, coins arrondis 18px |
| **Dashboard organisateur** | ~350 px de large, 200 px de haut | `object-fit: cover` |

---

## Design de billet (ticket design)

| Élément | Dimensions recommandées | Format | Notes |
|---------|------------------------|--------|-------|
| **Image PNG du billet** | **1000 × 400 px** (ratio 5:2) | PNG uniquement | Fond opaque, pas de transparence requise |
| **Zone QR Code** | minimum **150 × 150 px** | — | Positionnée via l'outil de sélection sur la page dédiée |

### Conseils pour le design de billet
- Laisser un espace libre d'au moins **150 × 150 px** pour le QR code
- Éviter de placer du texte important dans la zone QR (il sera recouvert)
- Le format paysage (horizontal) est recommandé pour une bonne lisibilité sur mobile
- Poids maximum : **5 Mo**

---

## Bannière accueil (placeholder)

| Élément | Dimensions | Emplacement |
|---------|------------|-------------|
| `banniere-home.jpg` | **800 × 500 px** (ratio ~16:10) | `/public/images/placeholders/` |

---

## Résumé rapide

| Usage | Dimensions | Ratio | Format |
|-------|-----------|-------|--------|
| Affiche événement | 1280 × 720 | 16:9 | JPEG/PNG/WebP |
| Design billet | 1000 × 400 | 5:2 | PNG |
| Bannière héros | 800 × 500 | 16:10 | JPEG |
