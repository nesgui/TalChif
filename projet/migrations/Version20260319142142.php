<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319142142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration vide - contenu déplacé dans Version20260316120204';
    }

    public function up(Schema $schema): void
    {
        // Rien à faire - déjà géré par Version20260316120204
    }

    public function down(Schema $schema): void
    {
        // Rien à faire
    }
}