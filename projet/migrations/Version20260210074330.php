<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210074330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add valide_par_id column to billet table';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la colonne valide_par_id
        $table = $schema->getTable('billet');
        $table->addColumn('valide_par_id', 'integer', ['notnull' => false]);
        
        // Ajouter la contrainte de clé étrangère
        $table->addForeignKeyConstraint(
            'user',
            ['valide_par_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'FK_BILLET_VALIDE_PAR'
        );
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('billet');
        
        // Supprimer la contrainte de clé étrangère
        $table->removeForeignKeyConstraint('FK_BILLET_VALIDE_PAR');
        
        // Supprimer la colonne
        $table->dropColumn('valide_par_id');
    }
}
