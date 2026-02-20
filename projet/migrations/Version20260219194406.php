<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219194406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket_design (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_billet VARCHAR(10) NOT NULL, design_path VARCHAR(500) NOT NULL, design_width INTEGER NOT NULL, design_height INTEGER NOT NULL, qr_x INTEGER DEFAULT NULL, qr_y INTEGER DEFAULT NULL, qr_w INTEGER DEFAULT NULL, qr_h INTEGER DEFAULT NULL, marker_color VARCHAR(7) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, evenement_id INTEGER NOT NULL, CONSTRAINT FK_B70D30FBFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B70D30FBFD02F13 ON ticket_design (evenement_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_design_evenement_type ON ticket_design (evenement_id, type_billet)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ticket_design');
    }
}
