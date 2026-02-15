<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout du champ `actif` (boolean) à la table user.
 * Tous les utilisateurs existants sont marqués comme actifs par défaut.
 */
final class Version20260215140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ actif à la table user pour permettre la désactivation des comptes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD actif TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN actif');
    }
}
