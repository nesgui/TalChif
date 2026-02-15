<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : Fusionner prenom + nom en un seul champ nom (nom complet),
 * puis supprimer la colonne prenom.
 */
final class Version20260215120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusionne prenom et nom en un seul champ nom (nom complet) et supprime la colonne prenom';
    }

    public function up(Schema $schema): void
    {
        // Étape 1 : Fusionner prenom + nom dans la colonne nom pour les données existantes
        $this->addSql("UPDATE user SET nom = CONCAT(COALESCE(prenom, ''), ' ', COALESCE(nom, '')) WHERE prenom IS NOT NULL AND prenom != ''");
        $this->addSql("UPDATE user SET nom = TRIM(nom)");

        // Étape 2 : Supprimer la colonne prenom
        $this->addSql('ALTER TABLE user DROP COLUMN prenom');
    }

    public function down(Schema $schema): void
    {
        // Étape 1 : Recréer la colonne prenom
        $this->addSql("ALTER TABLE user ADD prenom VARCHAR(255) NOT NULL DEFAULT ''");

        // Étape 2 : Tenter de séparer le nom complet (premier mot = prénom, reste = nom)
        $this->addSql("UPDATE user SET prenom = SUBSTRING_INDEX(nom, ' ', 1), nom = TRIM(SUBSTRING(nom FROM LOCATE(' ', nom) + 1)) WHERE nom LIKE '% %'");
    }
}
