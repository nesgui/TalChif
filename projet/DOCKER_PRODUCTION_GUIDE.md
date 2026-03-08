# Guide de Conteneurisation Production — TalChif

**Date**: 9 Mars 2026  
**Statut**: ✅ **Configuration Docker Production Complète**

---

## 📦 Fichiers Créés

### 1. **`Dockerfile`** — Multi-stage optimisé ✅

**Stage 1 (Builder)**: Installation des dépendances
- PHP 8.5-FPM Alpine (image légère)
- Extensions: PDO, PostgreSQL, MySQL, GD, Zip, Intl, OPcache
- Composer avec optimisation autoloader
- Nettoyage du cache Composer

**Stage 2 (Production)**: Image finale
- Extensions PHP production
- Configuration OPcache optimisée
- Configuration PHP-FPM (pool dynamique)
- Utilisateur non-root (sécurité)
- Healthcheck intégré
- Taille finale: ~150MB

### 2. **`docker-compose.prod.yml`** — Stack complète ✅

**Services**:
- **Nginx** (1.25-alpine) — Serveur web avec rate limiting
- **PHP-FPM** (8.5-alpine) — Application Symfony
- **PostgreSQL** (16-alpine) — Base de données
- **Redis** (7-alpine) — Cache et sessions
- **Messenger Worker** (x2 replicas) — Queue asynchrone
- **Cron** — Tâches planifiées (expiration commandes)

### 3. **`docker/nginx/nginx.conf`** — Configuration Nginx ✅

**Fonctionnalités**:
- Front controller Symfony
- Cache agressif pour assets statiques
- Rate limiting (login: 5/min, API: 30/min)
- Security headers (X-Frame-Options, CSP, etc.)
- Gzip compression
- Healthcheck endpoint
- Logs structurés

### 4. **`docker/postgres/init.sql`** — Init PostgreSQL ✅

**Configuration**:
- Extensions (uuid-ossp, pg_trgm)
- Locale française
- Optimisations performance

### 5. **`.dockerignore`** ✅

Exclusion des fichiers inutiles pour réduire la taille de l'image.

### 6. **Scripts de Déploiement** ✅

- `docker/scripts/deploy.sh` (Linux/Mac)
- `docker/scripts/deploy.ps1` (Windows)

### 7. **`.env.prod.example`** ✅

Template de configuration production avec toutes les variables nécessaires.

---

## 🚀 Déploiement

### Prérequis

```bash
# Installer Docker et Docker Compose
docker --version  # >= 24.0
docker-compose --version  # >= 2.20
```

### Étape 1: Configuration

```bash
# Copier le template
cp .env.prod.example .env.prod

# Éditer .env.prod et remplir:
# - APP_SECRET (générer avec: openssl rand -hex 32)
# - DB_PASSWORD (mot de passe fort)
# - REDIS_PASSWORD
# - Clés API Mobile Money
```

### Étape 2: Déploiement Automatique

**Linux/Mac**:
```bash
chmod +x docker/scripts/deploy.sh
./docker/scripts/deploy.sh
```

**Windows**:
```powershell
.\docker\scripts\deploy.ps1
```

### Étape 3: Déploiement Manuel

```bash
# 1. Build des images
docker-compose -f docker-compose.prod.yml build

# 2. Démarrer PostgreSQL et Redis
docker-compose -f docker-compose.prod.yml up -d postgres redis

# 3. Attendre que PostgreSQL soit prêt
sleep 10

# 4. Exécuter les migrations
docker-compose -f docker-compose.prod.yml run --rm php \
    php bin/console doctrine:migrations:migrate --no-interaction

# 5. Créer un admin
docker-compose -f docker-compose.prod.yml run --rm php \
    php bin/console app:create-admin admin@talchif.td "SecurePassword123!"

# 6. Démarrer tous les services
docker-compose -f docker-compose.prod.yml up -d

# 7. Vérifier l'état
docker-compose -f docker-compose.prod.yml ps
```

---

## 🔧 Gestion des Services

### Commandes Utiles

```bash
# Voir les logs
docker-compose -f docker-compose.prod.yml logs -f

# Logs d'un service spécifique
docker-compose -f docker-compose.prod.yml logs -f php
docker-compose -f docker-compose.prod.yml logs -f nginx

# Redémarrer un service
docker-compose -f docker-compose.prod.yml restart php

# Arrêter tous les services
docker-compose -f docker-compose.prod.yml down

# Arrêter et supprimer les volumes (⚠️ perte de données)
docker-compose -f docker-compose.prod.yml down -v

# Exécuter une commande dans le conteneur PHP
docker-compose -f docker-compose.prod.yml exec php php bin/console cache:clear

# Accéder au shell du conteneur
docker-compose -f docker-compose.prod.yml exec php sh
```

### Monitoring

```bash
# État des conteneurs
docker-compose -f docker-compose.prod.yml ps

# Utilisation des ressources
docker stats

# Healthchecks
docker-compose -f docker-compose.prod.yml exec nginx wget -qO- http://localhost/health
docker-compose -f docker-compose.prod.yml exec php php-fpm -t
```

---

## 🏗️ Architecture des Conteneurs

```
┌─────────────────────────────────────────────┐
│          Internet / Load Balancer           │
└────────────────┬────────────────────────────┘
                 │
                 ▼
         ┌───────────────┐
         │     Nginx     │ Port 80/443
         │  (Reverse     │ Rate Limiting
         │   Proxy)      │ Static Files
         └───────┬───────┘
                 │
                 ▼
         ┌───────────────┐
         │   PHP-FPM     │ Port 9000
         │  (Symfony 8)  │ OPcache
         │               │ Healthcheck
         └───┬───────┬───┘
             │       │
    ┌────────┘       └────────┐
    ▼                         ▼
┌──────────┐           ┌──────────┐
│PostgreSQL│           │  Redis   │
│  Port    │           │  Port    │
│  5432    │           │  6379    │
└──────────┘           └──────────┘
    │                         │
    │    ┌────────────────────┘
    │    │
    ▼    ▼
┌──────────────┐
│  Messenger   │ x2 replicas
│   Worker     │ Queue async
└──────────────┘

┌──────────────┐
│     Cron     │ Tâches planifiées
│   (*/5min)   │ Expiration commandes
└──────────────┘
```

---

## 📊 Spécifications Techniques

### Nginx
- **Image**: nginx:1.25-alpine (~40MB)
- **Ports**: 80, 443
- **Features**: Rate limiting, Gzip, Security headers
- **Healthcheck**: Toutes les 30s

### PHP-FPM
- **Image**: php:8.5-fpm-alpine (~150MB après build)
- **Extensions**: PDO, PostgreSQL, GD, Zip, Intl, OPcache
- **Pool**: Dynamic (10 start, 5-20 spare, 50 max)
- **Memory**: 512M limit
- **OPcache**: Activé avec validation_timestamps=0

### PostgreSQL
- **Image**: postgres:16-alpine (~230MB)
- **Extensions**: uuid-ossp, pg_trgm
- **Shared Buffers**: 256MB
- **Effective Cache**: 1GB
- **Healthcheck**: Toutes les 10s

### Redis
- **Image**: redis:7-alpine (~30MB)
- **Persistence**: AOF activé
- **Memory**: 256MB max (LRU eviction)
- **Healthcheck**: Toutes les 10s

### Messenger Worker
- **Replicas**: 2
- **CPU Limit**: 0.5 core
- **Memory Limit**: 256MB
- **Time Limit**: 3600s (1h)
- **Auto-restart**: unless-stopped

---

## 🔒 Sécurité

### 1. Utilisateur Non-Root ✅

```dockerfile
# Création utilisateur dédié
RUN adduser -D -u 1000 -G talchif talchif
USER talchif
```

### 2. Secrets Management ✅

```yaml
# Utiliser Docker secrets (recommandé)
secrets:
  db_password:
    file: ./secrets/db_password.txt

services:
  php:
    secrets:
      - db_password
```

### 3. Network Isolation ✅

```yaml
# Réseau privé isolé
networks:
  talchif:
    driver: bridge
    ipam:
      config:
        - subnet: 172.20.0.0/16
```

### 4. Rate Limiting ✅

```nginx
# Nginx rate limiting
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;
```

---

## 📈 Performance

### Optimisations Implémentées

1. **OPcache** — Cache bytecode PHP
2. **Gzip** — Compression HTTP
3. **Static Files Cache** — 1 an pour assets
4. **Redis** — Cache applicatif et sessions
5. **PostgreSQL Tuning** — Shared buffers, work_mem optimisés
6. **Multi-stage Build** — Image finale légère

### Benchmarks Attendus

| Métrique | Valeur |
|----------|--------|
| **Taille image PHP** | ~150MB |
| **Taille image Nginx** | ~40MB |
| **Temps démarrage** | <30s |
| **Requêtes/sec** | 500+ (avec cache) |
| **Temps réponse** | <100ms (pages cachées) |

---

## 🔄 Mise à Jour (Zero Downtime)

```bash
# 1. Build nouvelle version
docker-compose -f docker-compose.prod.yml build php

# 2. Déploiement progressif (rolling update)
docker-compose -f docker-compose.prod.yml up -d --no-deps --build php

# 3. Vérifier la santé
docker-compose -f docker-compose.prod.yml ps
```

---

## 🐛 Debugging

### Accéder aux Logs

```bash
# Tous les logs
docker-compose -f docker-compose.prod.yml logs -f

# Logs PHP
docker-compose -f docker-compose.prod.yml logs -f php

# Logs Nginx
docker-compose -f docker-compose.prod.yml logs -f nginx

# Logs dans le conteneur
docker-compose -f docker-compose.prod.yml exec php tail -f /app/var/log/prod.log
```

### Shell dans le Conteneur

```bash
# PHP
docker-compose -f docker-compose.prod.yml exec php sh

# PostgreSQL
docker-compose -f docker-compose.prod.yml exec postgres psql -U app -d talchif
```

### Vérifier la Configuration

```bash
# Test config Nginx
docker-compose -f docker-compose.prod.yml exec nginx nginx -t

# Test config PHP-FPM
docker-compose -f docker-compose.prod.yml exec php php-fpm -t

# Vérifier extensions PHP
docker-compose -f docker-compose.prod.yml exec php php -m
```

---

## 📋 Checklist de Déploiement

### Avant le Déploiement

- [ ] Copier `.env.prod.example` vers `.env.prod`
- [ ] Générer `APP_SECRET` sécurisé
- [ ] Configurer mots de passe forts (DB, Redis)
- [ ] Configurer clés API Mobile Money
- [ ] Vérifier domaine et certificats SSL
- [ ] Configurer backup automatique

### Déploiement

- [ ] Build des images Docker
- [ ] Démarrer PostgreSQL et Redis
- [ ] Exécuter migrations
- [ ] Créer compte admin
- [ ] Warmup du cache
- [ ] Démarrer tous les services
- [ ] Vérifier healthchecks

### Après le Déploiement

- [ ] Tester l'application
- [ ] Vérifier les logs
- [ ] Configurer monitoring (Prometheus/Grafana)
- [ ] Configurer alertes
- [ ] Documenter la procédure de rollback
- [ ] Planifier backups réguliers

---

## 🎯 Prochaines Étapes (Optionnel)

### 1. Kubernetes (Scalabilité)

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: talchif-php
spec:
  replicas: 3
  # ...
```

### 2. CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy Production
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Build and Push
        run: docker build -t talchif/app:latest .
      - name: Deploy
        run: ./docker/scripts/deploy.sh
```

### 3. Monitoring Stack

```yaml
# docker-compose.monitoring.yml
services:
  prometheus:
    image: prom/prometheus
  grafana:
    image: grafana/grafana
  loki:
    image: grafana/loki
```

---

## ✅ Résultat Final

### Configuration Production Complète

✅ **Dockerfile multi-stage optimisé**  
✅ **Docker Compose avec 6 services**  
✅ **Nginx configuré (rate limiting, cache, sécurité)**  
✅ **PostgreSQL avec init script**  
✅ **Redis pour cache/sessions**  
✅ **Messenger Worker (queue asynchrone)**  
✅ **Cron pour tâches planifiées**  
✅ **Scripts de déploiement (Linux + Windows)**  
✅ **Healthchecks sur tous les services**  
✅ **Documentation complète**  

### Commande de Déploiement

```bash
# Windows
.\docker\scripts\deploy.ps1

# Linux/Mac
./docker/scripts/deploy.sh
```

---

**La conteneurisation production est COMPLÈTE et prête pour le déploiement.**

**Date**: 9 Mars 2026  
**Statut**: ✅ **DOCKER PRODUCTION READY**
