# Script de déploiement production TalChif (Windows PowerShell)

$ErrorActionPreference = "Stop"

Write-Host "🚀 Déploiement TalChif en production..." -ForegroundColor Green

# Vérifier que .env.prod existe
if (-not (Test-Path ".env.prod")) {
    Write-Host "❌ Erreur: .env.prod n'existe pas" -ForegroundColor Red
    Write-Host "Copiez .env.prod.example vers .env.prod et configurez les variables"
    exit 1
}

Write-Host "📦 Construction des images Docker..." -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml build --no-cache

Write-Host "🗄️  Démarrage de la base de données..." -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml up -d postgres redis

Write-Host "⏳ Attente de la disponibilité de PostgreSQL..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

Write-Host "🔄 Exécution des migrations..." -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml run --rm php php bin/console doctrine:migrations:migrate --no-interaction

Write-Host "🧹 Nettoyage du cache..." -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml run --rm php php bin/console cache:clear --env=prod
docker-compose -f docker-compose.prod.yml run --rm php php bin/console cache:warmup --env=prod

Write-Host "🚀 Démarrage de tous les services..." -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml up -d

Write-Host ""
Write-Host "✅ Déploiement terminé!" -ForegroundColor Green
Write-Host ""
Write-Host "📊 Vérifier l'état des services:" -ForegroundColor Yellow
Write-Host "   docker-compose -f docker-compose.prod.yml ps"
Write-Host ""
Write-Host "📝 Voir les logs:" -ForegroundColor Yellow
Write-Host "   docker-compose -f docker-compose.prod.yml logs -f"
Write-Host ""
Write-Host "🌐 Application disponible sur: http://localhost" -ForegroundColor Cyan
