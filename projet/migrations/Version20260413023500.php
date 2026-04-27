<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413023500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le drapeau checkout_account pour les comptes client créés automatiquement pendant le paiement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD checkout_account BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP checkout_account');
    }
}
