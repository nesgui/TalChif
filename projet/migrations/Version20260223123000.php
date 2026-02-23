<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simplify ticket_design schema for fixed-frame tickets: drop qr_x/qr_y/qr_w/qr_h columns.';
    }

    public function up(Schema $schema): void
    {
        // SQLite: drop columns requires table recreation.
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_design AS SELECT id, type_billet, design_path, design_width, design_height, created_at, updated_at, evenement_id FROM ticket_design');
        $this->addSql('DROP TABLE ticket_design');
        $this->addSql('CREATE TABLE ticket_design (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_billet VARCHAR(10) NOT NULL, design_path VARCHAR(500) NOT NULL, design_width INTEGER NOT NULL, design_height INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, evenement_id INTEGER NOT NULL, CONSTRAINT FK_B70D30FBFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_design (id, type_billet, design_path, design_width, design_height, created_at, updated_at, evenement_id) SELECT id, type_billet, design_path, design_width, design_height, created_at, updated_at, evenement_id FROM __temp__ticket_design');
        $this->addSql('DROP TABLE __temp__ticket_design');
        $this->addSql('CREATE INDEX IDX_B70D30FBFD02F13 ON ticket_design (evenement_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_design_evenement_type ON ticket_design (evenement_id, type_billet)');
    }

    public function down(Schema $schema): void
    {
        // Restore legacy qr_* columns.
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_design AS SELECT id, type_billet, design_path, design_width, design_height, created_at, updated_at, evenement_id FROM ticket_design');
        $this->addSql('DROP TABLE ticket_design');
        $this->addSql('CREATE TABLE ticket_design (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_billet VARCHAR(10) NOT NULL, design_path VARCHAR(500) NOT NULL, design_width INTEGER NOT NULL, design_height INTEGER NOT NULL, qr_x INTEGER DEFAULT NULL, qr_y INTEGER DEFAULT NULL, qr_w INTEGER DEFAULT NULL, qr_h INTEGER DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, evenement_id INTEGER NOT NULL, CONSTRAINT FK_B70D30FBFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_design (id, type_billet, design_path, design_width, design_height, qr_x, qr_y, qr_w, qr_h, created_at, updated_at, evenement_id) SELECT id, type_billet, design_path, design_width, design_height, NULL, NULL, NULL, NULL, created_at, updated_at, evenement_id FROM __temp__ticket_design');
        $this->addSql('DROP TABLE __temp__ticket_design');
        $this->addSql('CREATE INDEX IDX_B70D30FBFD02F13 ON ticket_design (evenement_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_design_evenement_type ON ticket_design (evenement_id, type_billet)');
    }
}
