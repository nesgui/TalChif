# ============================================
# Stage 1: Builder - Installation des dépendances
# ============================================
FROM php:8.5-fpm-alpine AS builder

# Installation des dépendances système
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        zip \
        gd \
        intl \
        opcache \
        mbstring

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier les fichiers de dépendances
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./

# Installation des dépendances PHP (sans dev)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Copier le code source
COPY . .

# Générer l'autoloader optimisé
RUN composer dump-autoload --optimize --classmap-authoritative

# Nettoyer le cache Composer
RUN rm -rf /root/.composer

# ============================================
# Stage 2: Production - Image finale optimisée
# ============================================
FROM php:8.5-fpm-alpine

# Labels pour métadonnées
LABEL maintainer="TalChif Team"
LABEL version="1.0"
LABEL description="TalChif - Plateforme de billetterie événementielle"

# Installation des extensions PHP nécessaires en production
RUN apk add --no-cache \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu \
    postgresql-libs \
    oniguruma \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        zip \
        gd \
        intl \
        opcache \
        mbstring

# Configuration PHP pour production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'realpath_cache_size=4096K'; \
    echo 'realpath_cache_ttl=600'; \
    echo 'memory_limit=512M'; \
    echo 'max_execution_time=60'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=10M'; \
    echo 'expose_php=Off'; \
} > /usr/local/etc/php/conf.d/production.ini

# Configuration PHP-FPM pour production
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 50'; \
    echo 'pm.start_servers = 10'; \
    echo 'pm.min_spare_servers = 5'; \
    echo 'pm.max_spare_servers = 20'; \
    echo 'pm.max_requests = 500'; \
    echo 'pm.status_path = /fpm-status'; \
    echo 'ping.path = /fpm-ping'; \
} > /usr/local/etc/php-fpm.d/production.conf

# Créer utilisateur non-root
RUN addgroup -g 1000 talchif && \
    adduser -D -u 1000 -G talchif talchif

# Copier l'application depuis le builder
COPY --from=builder --chown=talchif:talchif /app /app

WORKDIR /app

# Créer les répertoires nécessaires avec bonnes permissions
RUN mkdir -p var/cache var/log public/images && \
    chown -R talchif:talchif var/ public/images/ && \
    chmod -R 775 var/ public/images/

# Passer à l'utilisateur non-root
USER talchif

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

# Exposer le port PHP-FPM
EXPOSE 9000

# Commande de démarrage
CMD ["php-fpm"]
