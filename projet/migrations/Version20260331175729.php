<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331175729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__billet AS SELECT id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, evenement_id, client_id, organisateur_id, valide_par_id, rendered_png_path FROM billet');
        $this->addSql('DROP TABLE billet');
        $this->addSql('CREATE TABLE billet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, qr_code VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, prix NUMERIC(10, 2) NOT NULL, is_valide BOOLEAN NOT NULL, is_utilise BOOLEAN NOT NULL, date_utilisation DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, transaction_id VARCHAR(255) DEFAULT NULL, statut_paiement VARCHAR(50) NOT NULL, evenement_id INTEGER NOT NULL, client_id INTEGER NOT NULL, organisateur_id INTEGER DEFAULT NULL, valide_par_id INTEGER DEFAULT NULL, rendered_png_path VARCHAR(500) DEFAULT NULL, CONSTRAINT FK_1F034AF6FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF619EB6921 FOREIGN KEY (client_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF6D936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF66AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO billet (id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, evenement_id, client_id, organisateur_id, valide_par_id, rendered_png_path) SELECT id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, evenement_id, client_id, organisateur_id, valide_par_id, rendered_png_path FROM __temp__billet');
        $this->addSql('DROP TABLE __temp__billet');
        $this->addSql('CREATE INDEX IDX_1F034AF6D936B2FA ON billet (organisateur_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF619EB6921 ON billet (client_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF6FD02F13 ON billet (evenement_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F034AF67D8B1FB5 ON billet (qr_code)');
        $this->addSql('CREATE INDEX IDX_1F034AF66AF12ED9 ON billet (valide_par_id)');
        $columns = $this->connection->createSchemaManager()->listTableColumns('commande');
        if (!isset($columns['capture_preuve_paiement'])) {
            $this->addSql('ALTER TABLE commande ADD COLUMN capture_preuve_paiement VARCHAR(255) DEFAULT NULL');
        }
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, actif BOOLEAN NOT NULL, balance INTEGER NOT NULL, verification_token VARCHAR(100) DEFAULT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance) SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__billet AS SELECT id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, rendered_png_path, evenement_id, client_id, organisateur_id, valide_par_id FROM billet');
        $this->addSql('DROP TABLE billet');
        $this->addSql('CREATE TABLE billet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, qr_code VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, prix NUMERIC(10, 2) NOT NULL, is_valide BOOLEAN NOT NULL, is_utilise BOOLEAN NOT NULL, date_utilisation DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, transaction_id VARCHAR(255) DEFAULT NULL, statut_paiement VARCHAR(50) NOT NULL, rendered_png_path VARCHAR(500) DEFAULT NULL, evenement_id INTEGER NOT NULL, client_id INTEGER NOT NULL, organisateur_id INTEGER DEFAULT NULL, valide_par_id INTEGER DEFAULT NULL, CONSTRAINT FK_1F034AF6FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF619EB6921 FOREIGN KEY (client_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF6D936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F034AF66AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO billet (id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, rendered_png_path, evenement_id, client_id, organisateur_id, valide_par_id) SELECT id, qr_code, type, prix, is_valide, is_utilise, date_utilisation, created_at, updated_at, transaction_id, statut_paiement, rendered_png_path, evenement_id, client_id, organisateur_id, valide_par_id FROM __temp__billet');
        $this->addSql('DROP TABLE __temp__billet');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F034AF67D8B1FB5 ON billet (qr_code)');
        $this->addSql('CREATE INDEX IDX_1F034AF6FD02F13 ON billet (evenement_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF619EB6921 ON billet (client_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF6D936B2FA ON billet (organisateur_id)');
        $this->addSql('CREATE INDEX IDX_1F034AF66AF12ED9 ON billet (valide_par_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__commande AS SELECT id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id FROM commande');
        $this->addSql('DROP TABLE commande');
        $this->addSql('CREATE TABLE commande (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reference VARCHAR(32) NOT NULL, montant_total NUMERIC(12, 2) NOT NULL, numero_client VARCHAR(20) NOT NULL, statut VARCHAR(50) NOT NULL, date_expiration DATETIME NOT NULL, created_at DATETIME NOT NULL, methode_paiement VARCHAR(20) NOT NULL, commission_plateforme NUMERIC(12, 2) NOT NULL, montant_net_organisateur NUMERIC(12, 2) NOT NULL, date_validation DATETIME DEFAULT NULL, tentative_validation INTEGER NOT NULL, deposit_id VARCHAR(255) DEFAULT NULL, reference_transaction_client VARCHAR(64) DEFAULT NULL, client_id INTEGER NOT NULL, valide_par_id INTEGER DEFAULT NULL, CONSTRAINT FK_6EEAA67D19EB6921 FOREIGN KEY (client_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6EEAA67D6AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO commande (id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id) SELECT id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id FROM __temp__commande');
        $this->addSql('DROP TABLE __temp__commande');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DAEA34913 ON commande (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D9815E4B1 ON commande (deposit_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D6AF12ED9 ON commande (valide_par_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, actif BOOLEAN NOT NULL, balance INTEGER DEFAULT 0 NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance) SELECT id, email, roles, password, nom, telephone, created_at, updated_at, is_verified, role, actif, balance FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
