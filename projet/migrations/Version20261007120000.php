<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cloisonnement au niveau de la base : Row Level Security.
 *
 * Le filtre Doctrine protege les requetes passant par l'ORM. Ces politiques
 * protegent la table elle-meme : un SQL brut, une console psql ou une faille
 * d'injection restent cloisonnes.
 *
 * L'organisation courante est transmise par un parametre de session
 * PostgreSQL, « talchif.organisation_id », pose par ContexteTenant. Parametre
 * absent : les politiques laissent tout passer, ce qui preserve le catalogue
 * public et la vue d'ensemble de l'administration — exactement la semantique du
 * filtre applicatif.
 *
 * FORCE ROW LEVEL SECURITY applique les politiques meme au proprietaire des
 * tables. Un role superuser reste exempt par conception PostgreSQL : c'est
 * pourquoi l'application doit se connecter avec le role non-proprietaire cree
 * par la commande app:securite:role-applicatif.
 */
final class Version20261007120000 extends AbstractMigration
{
    /** Tables portant des donnees de vendeur. */
    private const TABLES = ['evenement', 'billet', 'commande_ligne', 'ticket_design'];

    private const POLITIQUE = 'cloisonnement_organisation';

    public function getDescription(): string
    {
        return 'Row Level Security sur les tables cloisonnees (defense en profondeur).';
    }

    public function up(Schema $schema): void
    {
        // Lecture du parametre de session, centralisee pour ne pas repeter
        // l'expression dans chaque politique.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION talchif_organisation_courante() RETURNS integer
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('talchif.organisation_id', true), '')::integer
            $$
        SQL);

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            $this->addSql(sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));

            // USING filtre les lectures, mises a jour et suppressions.
            // WITH CHECK empeche d'ecrire dans une autre organisation.
            $this->addSql(sprintf(
                <<<'SQL'
                    CREATE POLICY %s ON %s
                    FOR ALL
                    USING (
                        talchif_organisation_courante() IS NULL
                        OR organisation_id = talchif_organisation_courante()
                    )
                    WITH CHECK (
                        talchif_organisation_courante() IS NULL
                        OR organisation_id = talchif_organisation_courante()
                    )
                SQL,
                self::POLITIQUE,
                $table
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('DROP POLICY IF EXISTS %s ON %s', self::POLITIQUE, $table));
            $this->addSql(sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }

        $this->addSql('DROP FUNCTION IF EXISTS talchif_organisation_courante()');
    }
}
