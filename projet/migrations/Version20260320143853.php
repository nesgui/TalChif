<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320143853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande ADD COLUMN capture_preuve_paiement VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__commande AS SELECT id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id FROM commande');
        $this->addSql('DROP TABLE commande');
        $this->addSql('CREATE TABLE commande (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reference VARCHAR(32) NOT NULL, montant_total NUMERIC(12, 2) NOT NULL, numero_client VARCHAR(20) NOT NULL, statut VARCHAR(50) NOT NULL, date_expiration DATETIME NOT NULL, created_at DATETIME NOT NULL, methode_paiement VARCHAR(20) NOT NULL, commission_plateforme NUMERIC(12, 2) NOT NULL, montant_net_organisateur NUMERIC(12, 2) NOT NULL, date_validation DATETIME DEFAULT NULL, tentative_validation INTEGER NOT NULL, deposit_id VARCHAR(255) DEFAULT NULL, reference_transaction_client VARCHAR(64) DEFAULT NULL, client_id INTEGER NOT NULL, valide_par_id INTEGER DEFAULT NULL, CONSTRAINT FK_6EEAA67D19EB6921 FOREIGN KEY (client_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6EEAA67D6AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO commande (id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id) SELECT id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement, commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, deposit_id, reference_transaction_client, client_id, valide_par_id FROM __temp__commande');
        $this->addSql('DROP TABLE __temp__commande');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DAEA34913 ON commande (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D9815E4B1 ON commande (deposit_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D6AF12ED9 ON commande (valide_par_id)');
    }
}
