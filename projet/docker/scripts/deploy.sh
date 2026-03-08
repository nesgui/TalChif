#!/bin/sh
# Script de déploiement production TalChif

set -e

echo "🚀 Déploiement TalChif en production..."

# Vérifier que .env.prod existe
if [ ! -f .env.prod ]; then
    echo "❌ Erreur: .env.prod n'existe pas"
    echo "Copiez .env.prod.example vers .env.prod et configurez les variables"
    exit 1
fi

# Charger les variables d'environnement
export $(cat .env.prod | grep -v '^#' | xargs)

echo "📦 Construction des images Docker..."
docker-compose -f docker-compose.prod.yml build --no-cache

echo "🗄️  Démarrage de la base de données..."
docker-compose -f docker-compose.prod.yml up -d postgres redis

echo "⏳ Attente de la disponibilité de PostgreSQL..."
sleep 10

echo "🔄 Exécution des migrations..."
docker-compose -f docker-compose.prod.yml run --rm php php bin/console doctrine:migrations:migrate --no-interaction

echo "🧹 Nettoyage du cache..."
docker-compose -f docker-compose.prod.yml run --rm php php bin/console cache:clear --env=prod
docker-compose -f docker-compose.prod.yml run --rm php php bin/console cache:warmup --env=prod

echo "🚀 Démarrage de tous les services..."
docker-compose -f docker-compose.prod.yml up -d

echo "✅ Déploiement terminé!"
echo ""
echo "📊 Vérifier l'état des services:"
echo "   docker-compose -f docker-compose.prod.yml ps"
echo ""
echo "📝 Voir les logs:"
echo "   docker-compose -f docker-compose.prod.yml logs -f"
echo ""
echo "🌐 Application disponible sur: http://localhost"
