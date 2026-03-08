-- Script d'initialisation PostgreSQL pour TalChif
-- Exécuté automatiquement au premier démarrage du conteneur

-- Créer les extensions nécessaires
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- Pour recherche full-text performante

-- Configuration de la locale française
SET lc_messages TO 'fr_FR.UTF-8';
SET lc_monetary TO 'fr_FR.UTF-8';
SET lc_numeric TO 'fr_FR.UTF-8';
SET lc_time TO 'fr_FR.UTF-8';

-- Optimisations pour les performances
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '1GB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
ALTER SYSTEM SET wal_buffers = '16MB';
ALTER SYSTEM SET default_statistics_target = 100;
ALTER SYSTEM SET random_page_cost = 1.1;
ALTER SYSTEM SET effective_io_concurrency = 200;
ALTER SYSTEM SET work_mem = '4MB';
ALTER SYSTEM SET min_wal_size = '1GB';
ALTER SYSTEM SET max_wal_size = '4GB';

-- Message de confirmation
DO $$
BEGIN
    RAISE NOTICE 'Base de données TalChif initialisée avec succès';
END $$;
