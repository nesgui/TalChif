<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260306093036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement ADD COLUMN categorie VARCHAR(50) DEFAULT \'autre\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__evenement AS SELECT id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, is_active, is_valide, created_at, updated_at, organisateur_paye, organisateur_id FROM evenement');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('CREATE TABLE evenement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description CLOB NOT NULL, slug VARCHAR(255) NOT NULL, date_evenement DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, places_disponibles INTEGER NOT NULL, places_vendues INTEGER NOT NULL, prix_simple NUMERIC(10, 2) NOT NULL, prix_vip NUMERIC(10, 2) DEFAULT NULL, affiche_principale VARCHAR(500) DEFAULT NULL, autres_affiches CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, is_valide BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, organisateur_paye BOOLEAN NOT NULL, organisateur_id INTEGER NOT NULL, CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO evenement (id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, is_active, is_valide, created_at, updated_at, organisateur_paye, organisateur_id) SELECT id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, is_active, is_valide, created_at, updated_at, organisateur_paye, organisateur_id FROM __temp__evenement');
        $this->addSql('DROP TABLE __temp__evenement');
        $this->addSql('CREATE INDEX IDX_B26681ED936B2FA ON evenement (organisateur_id)');
    }
}
