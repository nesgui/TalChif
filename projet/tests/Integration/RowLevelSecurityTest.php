<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Evenement;
use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Tenant\ContexteTenant;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifie que le cloisonnement tient au niveau de la base, pas seulement de
 * l'ORM.
 *
 * Ces cas s'executent sur une connexion ouverte avec le role applicatif
 * non-proprietaire : un superuser ou le proprietaire des tables contournent les
 * politiques, c'est precisement pourquoi l'application ne doit pas s'y
 * connecter en production.
 *
 * Le mot de passe du role vient de APP_DB_ROLE_PASSWORD. Sans lui, les cas sont
 * ignores plutot que faussement verts.
 */
final class RowLevelSecurityTest extends KernelTestCase
{
    private const ROLE = 'talchif_app';

    private EntityManagerInterface $em;
    private Connection $connexionRole;
    private int $idAlpha;
    private int $idBeta;

    protected function setUp(): void
    {
        $motDePasse = (string) ($_ENV['APP_DB_ROLE_PASSWORD'] ?? getenv('APP_DB_ROLE_PASSWORD') ?: '');
        if ($motDePasse === '') {
            self::markTestSkipped('APP_DB_ROLE_PASSWORD absent : role applicatif non testable.');
        }

        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->getConnection()->executeStatement(
            'TRUNCATE billet, commande_ligne, commande, log_securite, app_setting, ticket_design, evenement, membre_organisation, organisation, utilisateur RESTART IDENTITY CASCADE'
        );

        [$this->idAlpha, $this->idBeta] = $this->semerDeuxOrganisations();
        $this->connexionRole = $this->ouvrirConnexionRole($motDePasse);
    }

    protected function tearDown(): void
    {
        if (isset($this->connexionRole)) {
            $this->connexionRole->close();
        }

        parent::tearDown();
    }

    public function testSansContexteLeRoleVoitToutesLesOrganisations(): void
    {
        self::assertSame(
            2,
            $this->compterEvenements(null),
            'parametre absent : les politiques laissent passer, le catalogue public reste visible'
        );
    }

    public function testAvecContexteLeRoleNeVoitQueSonOrganisation(): void
    {
        self::assertSame(1, $this->compterEvenements($this->idAlpha));
        self::assertSame(1, $this->compterEvenements($this->idBeta));
    }

    public function testUnSqlBrutNePeutPasLireUneAutreOrganisation(): void
    {
        $this->poserContexte($this->idAlpha);

        self::assertSame(
            0,
            (int) $this->connexionRole->fetchOne("SELECT count(*) FROM evenement WHERE slug = 'concert-beta'"),
            'une requete SQL brute, hors ORM, doit rester cloisonnee'
        );
    }

    public function testUneMiseAJourCroiseeNAtteintAucuneLigne(): void
    {
        $this->poserContexte($this->idAlpha);

        $lignes = $this->connexionRole->executeStatement(
            "UPDATE evenement SET nom = 'DETOURNE' WHERE slug = 'concert-beta'"
        );
        self::assertSame(0, $lignes);

        // Verifie depuis le compte proprietaire que rien n'a bouge.
        self::assertSame(
            'Concert Beta',
            $this->em->getConnection()->fetchOne("SELECT nom FROM evenement WHERE slug = 'concert-beta'")
        );
    }

    public function testUneInsertionDansUneAutreOrganisationEstRefusee(): void
    {
        $this->poserContexte($this->idAlpha);

        $this->expectExceptionMessageMatches('/row-level security/i');

        $this->connexionRole->executeStatement(
            <<<'SQL'
                INSERT INTO evenement
                  (nom, description, slug, date_evenement, lieu, adresse, ville,
                   places_disponibles, places_vendues, prix_simple, categorie,
                   is_active, is_valide, created_at, organisateur_paye,
                   organisateur_id, organisation_id)
                SELECT 'Injecte', 'd', 'ev-injecte', NOW(), 'L', 'A', 'N',
                       10, 0, 1000, 'autre', true, true, NOW(), false,
                       e.organisateur_id, :organisation
                FROM evenement e LIMIT 1
            SQL,
            ['organisation' => $this->idBeta]
        );
    }

    public function testLeRoleApplicatifNePeutPasModifierLeSchema(): void
    {
        $this->expectExceptionMessageMatches('/permission denied|droit refus/i');

        $this->connexionRole->executeStatement('CREATE TABLE intrusion (id INT)');
    }

    public function testLeRoleApplicatifNEstNiSuperuserNiExemptDeRls(): void
    {
        $profil = $this->em->getConnection()->fetchAssociative(
            'SELECT rolsuper, rolbypassrls, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = ?',
            [self::ROLE]
        );

        self::assertNotFalse($profil, 'le role applicatif doit exister');
        self::assertFalse((bool) $profil['rolsuper'], 'un superuser contourne RLS');
        self::assertFalse((bool) $profil['rolbypassrls'], 'BYPASSRLS annulerait le cloisonnement');
        self::assertFalse((bool) $profil['rolcreatedb']);
        self::assertFalse((bool) $profil['rolcreaterole']);
    }

    public function testLesQuatreTablesCloisonneesForcentRls(): void
    {
        $tables = $this->em->getConnection()->fetchAllAssociativeIndexed(
            <<<'SQL'
                SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'
                  AND c.relname IN ('evenement', 'billet', 'commande_ligne', 'ticket_design')
            SQL
        );

        self::assertCount(4, $tables);
        foreach ($tables as $nom => $etat) {
            self::assertTrue((bool) $etat['relrowsecurity'], "RLS inactif sur {$nom}");
            self::assertTrue((bool) $etat['relforcerowsecurity'], "FORCE RLS inactif sur {$nom}");
        }
    }

    /**
     * ContexteTenant doit transmettre l'organisation a la base, pas seulement
     * au filtre Doctrine.
     */
    public function testEtablirLeContextePoseLeParametreDeSession(): void
    {
        $contexte = static::getContainer()->get(ContexteTenant::class);
        $organisation = $this->em->getRepository(Organisation::class)->find($this->idAlpha);

        $contexte->etablir($organisation);
        self::assertSame(
            (string) $this->idAlpha,
            $this->em->getConnection()->fetchOne("SELECT current_setting('talchif.organisation_id', true)")
        );

        $contexte->liberer();
        self::assertSame(
            '',
            $this->em->getConnection()->fetchOne("SELECT current_setting('talchif.organisation_id', true)"),
            'la liberation doit remettre le parametre a zero : la connexion est reutilisee'
        );
    }

    private function compterEvenements(?int $organisation): int
    {
        $this->poserContexte($organisation);

        return (int) $this->connexionRole->fetchOne('SELECT count(*) FROM evenement');
    }

    private function poserContexte(?int $organisation): void
    {
        $this->connexionRole->executeStatement(
            'SELECT set_config(?, ?, false)',
            [ContexteTenant::PARAMETRE_BASE, $organisation === null ? '' : (string) $organisation]
        );
    }

    /**
     * Connexion avec le role applicatif, derivee des parametres courants.
     */
    private function ouvrirConnexionRole(string $motDePasse): Connection
    {
        $parametres = $this->em->getConnection()->getParams();

        return DriverManager::getConnection([
            'driver' => $parametres['driver'] ?? 'pdo_pgsql',
            'host' => $parametres['host'] ?? '127.0.0.1',
            'port' => $parametres['port'] ?? 5432,
            'dbname' => $parametres['dbname'] ?? 'app',
            'user' => self::ROLE,
            'password' => $motDePasse,
        ]);
    }

    /**
     * @return array{int, int}
     */
    private function semerDeuxOrganisations(): array
    {
        $identifiants = [];

        foreach ([['Alpha', 'concert-alpha'], ['Beta', 'concert-beta']] as [$nom, $slug]) {
            $utilisateur = new User();
            $utilisateur->setEmail(mb_strtolower($nom) . '@talchif.td');
            $utilisateur->setNom('Proprietaire ' . $nom);
            $utilisateur->setRole('ORGANISATEUR');
            $utilisateur->setActif(true);
            $utilisateur->setIsVerified(true);
            $utilisateur->setPassword('hachage-factice');
            $this->em->persist($utilisateur);

            $organisation = new Organisation();
            $organisation->setNom('Organisation ' . $nom);
            $organisation->setSlug(mb_strtolower($nom));
            $organisation->setActif(true);
            $this->em->persist($organisation);

            $membre = new MembreOrganisation();
            $membre->setOrganisation($organisation);
            $membre->setUtilisateur($utilisateur);
            $membre->setRole(MembreOrganisation::ROLE_PROPRIETAIRE);
            $this->em->persist($membre);

            $evenement = new Evenement();
            $evenement->setNom('Concert ' . $nom);
            $evenement->setSlug($slug);
            $evenement->setDescription('Jeu de test');
            $evenement->setDateEvenement(new \DateTimeImmutable('+30 days'));
            $evenement->setLieu('Salle');
            $evenement->setAdresse('1 rue du test');
            $evenement->setVille('Ndjamena');
            $evenement->setPlacesDisponibles(50);
            $evenement->setPlacesVendues(0);
            $evenement->setPrixSimple(5000.0);
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $evenement->setOrganisateur($utilisateur);
            $evenement->setOrganisation($organisation);
            $this->em->persist($evenement);

            $this->em->flush();
            $identifiants[] = (int) $organisation->getId();
        }

        return [$identifiants[0], $identifiants[1]];
    }
}
