<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208132244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, qr_code VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, prix NUMERIC(10, 2) NOT NULL, is_valide BOOLEAN NOT NULL, is_utilise BOOLEAN NOT NULL, date_utilisation DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, transaction_id VARCHAR(255) DEFAULT NULL, statut_paiement VARCHAR(50) NOT NULL, evenement_id INTEGER NOT NULL, client_id INTEGER NOT NULL, organisateur_id INTEGER DEFAULT NULL, CONSTRAINT FK_1F034AF6FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF619EB6921 FOREIGN KEY (client_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF6D936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F034AF67D8B1FB5 ON billet (qr_code)');
        $this->addSql('CREATE INDEX IDX_1F034AF6FD02F13 ON billet (evenement_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF619EB6921 ON billet (client_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF6D936B2FA ON billet (organisateur_id)');
        $this->addSql('CREATE TABLE evenement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description CLOB NOT NULL, slug VARCHAR(255) NOT NULL, date_evenement DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, places_disponibles INTEGER NOT NULL, places_vendues INTEGER NOT NULL, prix_simple NUMERIC(10, 2) NOT NULL, prix_vip NUMERIC(10, 2) DEFAULT NULL, affiche_principale VARCHAR(500) DEFAULT NULL, autres_affiches CLOB DEFAULT NULL, image_billet VARCHAR(500) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_valide BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, organisateur_id INTEGER NOT NULL, CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B26681ED936B2FA ON evenement (organisateur_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE billet');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
