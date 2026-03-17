<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute reference_transaction_client sur commande';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD COLUMN reference_transaction_client VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP COLUMN reference_transaction_client');
    }
}

