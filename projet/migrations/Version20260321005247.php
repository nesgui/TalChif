<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321005247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD COLUMN verification_token VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, actif BOOLEAN NOT NULL, balance INTEGER NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance) SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
