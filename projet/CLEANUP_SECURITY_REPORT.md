# Rapport de Nettoyage et Sécurité — TalChif

**Date**: 9 Mars 2026  
**Statut**: 🔍 Analyse en cours

---

## 🔍 Fichiers Identifiés pour Nettoyage

### 🔴 HAUTE PRIORITÉ — Sécurité

#### 1. `.env` — APP_SECRET vide ⚠️
**Fichier**: `.env` ligne 19
```env
APP_SECRET=
```
**Risque**: Sessions non sécurisées, CSRF vulnérable
**Action**: Générer un secret fort

#### 2. `migrate_with_confirm.php` — Script dangereux 🚨
**Fichier**: `migrate_with_confirm.php`
```php
echo "y" | passthru('php bin/console doctrine:migrations:migrate');
```
**Risque**: 
- Exécution de migrations sans confirmation (dangereux en prod)
- Accessible publiquement si mal configuré
- Aucune utilité (commande CLI existe déjà)

**Action**: ✅ SUPPRIMER

### 🟡 MOYENNE PRIORITÉ — Fichiers Obsolètes

#### 3. Templates Inutilisés
**Fichiers**:
- `templates/auth/login_new.html.twig` — Référencé dans AuthController mais jamais utilisé
- `templates/auth/register_new.html.twig` — Référencé dans AuthController mais jamais utilisé
- `templates/layout/auth_final.html.twig` — Layout non utilisé
- `templates/accueil/index_old.html.twig` — Ancienne version
- `templates/evenement/show_backup.html.twig` — Backup non nécessaire

**Risque**: 
- Confusion pour les développeurs
- Code mort qui peut contenir des failles
- Augmente la surface d'attaque

**Action**: ✅ SUPPRIMER (après vérification qu'ils ne sont pas utilisés)

#### 4. Documentation Redondante (8 fichiers NOTIFICATION_*.md)
**Fichiers**:
- `NOTIFICATION_SYSTEM_README.md`
- `NOTIFICATION_SYSTEM_MIGRATION.md`
- `NOTIFICATION_SYSTEM_AUDIT.md`
- `NOTIFICATION_QUICKSTART.md`
- `NOTIFICATION_PRESENTATION.md`
- `NOTIFICATION_INDEX.md`
- `NOTIFICATION_EXAMPLES.md`
- `NOTIFICATION_CHANGELOG.md`

**Risque**: Faible (documentation)
**Action**: ✅ CONSOLIDER en 1 seul fichier `docs/NOTIFICATIONS.md`

#### 5. Fichiers de Documentation Racine (13 fichiers .md)
**Fichiers**:
- `ARCHITECTURE_REVIEW.md` (ancien, remplacé par ARCHITECTURE_ANALYSIS_2026.md)
- `CRITICAL_FIXES.md` (obsolète)
- `fonctionnalites_desactiver.md` (à déplacer dans docs/)
- `dimensions_images.md` (à déplacer dans docs/)

**Action**: ✅ DÉPLACER vers `docs/` ou SUPPRIMER si obsolètes

### 🟢 BASSE PRIORITÉ — Optimisation

#### 6. Services Legacy Non Utilisés
**Fichiers**:
- `src/Service/Achat/ServiceAchat.php` — ✅ Remplacé par `AcheterBilletsHandler`
- `src/Service/Commande/ServiceCommande.php` — ⚠️ Encore utilisé dans certains contrôleurs

**Vérification nécessaire**: Grep pour voir les usages restants

#### 7. node_modules vide
**Dossier**: `node_modules/` (0 items)
**Action**: ✅ Laisser (géré par .gitignore)

---

## 📋 Plan de Nettoyage Sécurisé

### Phase 1: Sécurité Critique (IMMÉDIAT)

1. ✅ Générer APP_SECRET
2. ✅ Supprimer `migrate_with_confirm.php`
3. ✅ Vérifier que `.env` est dans `.gitignore`
4. ✅ Ajouter `.env.prod` dans `.gitignore`

### Phase 2: Nettoyage Templates (SANS RISQUE)

1. ✅ Supprimer templates inutilisés après vérification
2. ✅ Supprimer layouts obsolètes

### Phase 3: Organisation Documentation

1. ✅ Créer `docs/archive/` pour anciens docs
2. ✅ Déplacer fichiers .md racine vers `docs/`
3. ✅ Consolider documentation NOTIFICATION

### Phase 4: Services Legacy (VÉRIFICATION REQUISE)

1. ⚠️ Vérifier usages de `ServiceCommande`
2. ⚠️ Vérifier usages de `ServiceAchat`
3. ✅ Supprimer si plus utilisés

---

## ✅ Actions à Effectuer

### Suppressions Sûres (Pas de risque)

```bash
# Fichiers à supprimer
rm migrate_with_confirm.php
rm templates/evenement/show_backup.html.twig
rm templates/accueil/index_old.html.twig
```

### Vérifications Nécessaires

```bash
# Vérifier usages de login_new.html.twig
grep -r "login_new" src/

# Vérifier usages de register_new.html.twig
grep -r "register_new" src/

# Vérifier usages de ServiceCommande
grep -r "ServiceCommande" src/Controller/
```

---

**Statut**: Analyse terminée, actions identifiées
