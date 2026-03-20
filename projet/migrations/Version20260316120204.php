<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316120204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute deposit_id sur commande et balance sur user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD COLUMN deposit_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D9815E4B1 ON commande (deposit_id)');
        $this->addSql('ALTER TABLE user ADD COLUMN balance INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6EEAA67D9815E4B1');
        $this->addSql('ALTER TABLE commande DROP COLUMN deposit_id');
        $this->addSql('ALTER TABLE user DROP COLUMN balance');
    }
}