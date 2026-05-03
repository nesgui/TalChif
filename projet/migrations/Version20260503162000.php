<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Support checkout invité: client nullable + ajout checkout_email et access_token sur commande';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        // SQLite: pas de ALTER COLUMN, on rebuild la table.
        if ($isSqlite) {
            $this->addSql('PRAGMA foreign_keys=OFF');

            $this->addSql('ALTER TABLE commande RENAME TO commande_old');

            $this->addSql('CREATE TABLE commande (
                id INTEGER NOT NULL,
                reference VARCHAR(32) NOT NULL,
                montant_total NUMERIC(12, 2) NOT NULL,
                numero_client VARCHAR(20) NOT NULL,
                statut VARCHAR(50) NOT NULL,
                date_expiration DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                methode_paiement VARCHAR(20) NOT NULL,
                commission_plateforme NUMERIC(12, 2) NOT NULL,
                montant_net_organisateur NUMERIC(12, 2) NOT NULL,
                date_validation DATETIME DEFAULT NULL,
                tentative_validation INTEGER NOT NULL,
                client_id INTEGER DEFAULT NULL,
                valide_par_id INTEGER DEFAULT NULL,
                deposit_id VARCHAR(255) DEFAULT NULL,
                reference_transaction_client VARCHAR(64) DEFAULT NULL,
                capture_preuve_paiement VARCHAR(255) DEFAULT NULL,
                checkout_email VARCHAR(180) DEFAULT NULL,
                access_token VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY(id)
            )');

            $this->addSql('INSERT INTO commande (
                id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement,
                commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, client_id,
                valide_par_id, deposit_id, reference_transaction_client, capture_preuve_paiement
            )
            SELECT
                id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement,
                commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, client_id,
                valide_par_id, deposit_id, reference_transaction_client, capture_preuve_paiement
            FROM commande_old');

            $this->addSql('DROP TABLE commande_old');

            // Index/unique existants
            $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DAEA34913 ON commande (reference)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D9815E4B1 ON commande (deposit_id)');
            $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
            $this->addSql('CREATE INDEX IDX_6EEAA67D6AF12ED9 ON commande (valide_par_id)');

            // Nouveaux index
            $this->addSql('CREATE INDEX IDX_COMMANDE_CHECKOUT_EMAIL ON commande (checkout_email)');
            $this->addSql('CREATE INDEX IDX_COMMANDE_ACCESS_TOKEN ON commande (access_token)');

            $this->addSql('PRAGMA foreign_keys=ON');
            return;
        }

        // MySQL/MariaDB
        $this->addSql('ALTER TABLE commande MODIFY client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD checkout_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD access_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_COMMANDE_CHECKOUT_EMAIL ON commande (checkout_email)');
        $this->addSql('CREATE INDEX IDX_COMMANDE_ACCESS_TOKEN ON commande (access_token)');
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('PRAGMA foreign_keys=OFF');

            $this->addSql('ALTER TABLE commande RENAME TO commande_old');

            $this->addSql('CREATE TABLE commande (
                id INTEGER NOT NULL,
                reference VARCHAR(32) NOT NULL,
                montant_total NUMERIC(12, 2) NOT NULL,
                numero_client VARCHAR(20) NOT NULL,
                statut VARCHAR(50) NOT NULL,
                date_expiration DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                methode_paiement VARCHAR(20) NOT NULL,
                commission_plateforme NUMERIC(12, 2) NOT NULL,
                montant_net_organisateur NUMERIC(12, 2) NOT NULL,
                date_validation DATETIME DEFAULT NULL,
                tentative_validation INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                valide_par_id INTEGER DEFAULT NULL,
                deposit_id VARCHAR(255) DEFAULT NULL,
                reference_transaction_client VARCHAR(64) DEFAULT NULL,
                capture_preuve_paiement VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY(id)
            )');

            $this->addSql('INSERT INTO commande (
                id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement,
                commission_plateforme, montant_net_organisateur, date_validation, tentative_validation, client_id,
                valide_par_id, deposit_id, reference_transaction_client, capture_preuve_paiement
            )
            SELECT
                id, reference, montant_total, numero_client, statut, date_expiration, created_at, methode_paiement,
                commission_plateforme, montant_net_organisateur, date_validation, tentative_validation,
                COALESCE(client_id, 0) AS client_id,
                valide_par_id, deposit_id, reference_transaction_client, capture_preuve_paiement
            FROM commande_old');

            $this->addSql('DROP TABLE commande_old');

            $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DAEA34913 ON commande (reference)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D9815E4B1 ON commande (deposit_id)');
            $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
            $this->addSql('CREATE INDEX IDX_6EEAA67D6AF12ED9 ON commande (valide_par_id)');

            $this->addSql('PRAGMA foreign_keys=ON');
            return;
        }

        $this->addSql('DROP INDEX IDX_COMMANDE_CHECKOUT_EMAIL ON commande');
        $this->addSql('DROP INDEX IDX_COMMANDE_ACCESS_TOKEN ON commande');
        $this->addSql('ALTER TABLE commande DROP checkout_email');
        $this->addSql('ALTER TABLE commande DROP access_token');
        $this->addSql('ALTER TABLE commande MODIFY client_id INT NOT NULL');
    }
}
