<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217095745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reference VARCHAR(32) NOT NULL, montant_total NUMERIC(12, 2) NOT NULL, numero_client VARCHAR(20) NOT NULL, statut VARCHAR(50) NOT NULL, date_expiration DATETIME NOT NULL, created_at DATETIME NOT NULL, methode_paiement VARCHAR(20) NOT NULL, commission_plateforme NUMERIC(12, 2) NOT NULL, montant_net_organisateur NUMERIC(12, 2) NOT NULL, date_validation DATETIME DEFAULT NULL, tentative_validation INTEGER NOT NULL, client_id INTEGER NOT NULL, valide_par_id INTEGER DEFAULT NULL, CONSTRAINT FK_6EEAA67D19EB6921 FOREIGN KEY (client_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6EEAA67D6AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DAEA34913 ON commande (reference)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D6AF12ED9 ON commande (valide_par_id)');
        $this->addSql('CREATE TABLE commande_ligne (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quantite INTEGER NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, type_billet VARCHAR(10) NOT NULL, commande_id INTEGER NOT NULL, evenement_id INTEGER NOT NULL, CONSTRAINT FK_6E98044082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6E980440FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6E98044082EA2E54 ON commande_ligne (commande_id)');
        $this->addSql('CREATE INDEX IDX_6E980440FD02F13 ON commande_ligne (evenement_id)');
        $this->addSql('CREATE TABLE log_securite (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(50) NOT NULL, reference_commande VARCHAR(255) DEFAULT NULL, details CLOB DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, utilisateur_id INTEGER DEFAULT NULL, CONSTRAINT FK_BFC94251FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BFC94251FB88E14F ON log_securite (utilisateur_id)');
        $this->addSql('ALTER TABLE evenement ADD COLUMN organisateur_paye BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, actif BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif) SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commande_ligne');
        $this->addSql('DROP TABLE log_securite');
        $this->addSql('CREATE TEMPORARY TABLE __temp__evenement AS SELECT id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, image_billet, is_active, is_valide, created_at, updated_at, organisateur_id FROM evenement');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('CREATE TABLE evenement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description CLOB NOT NULL, slug VARCHAR(255) NOT NULL, date_evenement DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, places_disponibles INTEGER NOT NULL, places_vendues INTEGER NOT NULL, prix_simple NUMERIC(10, 2) NOT NULL, prix_vip NUMERIC(10, 2) DEFAULT NULL, affiche_principale VARCHAR(500) DEFAULT NULL, autres_affiches CLOB DEFAULT NULL, image_billet VARCHAR(500) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_valide BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, organisateur_id INTEGER NOT NULL, CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO evenement (id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, image_billet, is_active, is_valide, created_at, updated_at, organisateur_id) SELECT id, nom, description, slug, date_evenement, lieu, adresse, ville, places_disponibles, places_vendues, prix_simple, prix_vip, affiche_principale, autres_affiches, image_billet, is_active, is_valide, created_at, updated_at, organisateur_id FROM __temp__evenement');
        $this->addSql('DROP TABLE __temp__evenement');
        $this->addSql('CREATE INDEX IDX_B26681ED936B2FA ON evenement (organisateur_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, actif BOOLEAN DEFAULT 1 NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif) SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
