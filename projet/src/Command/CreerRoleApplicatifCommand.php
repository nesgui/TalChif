<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cree le role PostgreSQL avec lequel l'application se connecte.
 *
 * Ce role n'est ni superuser ni proprietaire des tables : c'est la condition
 * pour que les politiques Row Level Security s'appliquent reellement. Un
 * superuser, et un proprietaire hors FORCE ROW LEVEL SECURITY, les contournent.
 *
 * Il ne recoit que des droits de donnees (SELECT/INSERT/UPDATE/DELETE), aucun
 * droit de schema : les migrations continuent de tourner avec le compte
 * proprietaire.
 *
 * La commande est idempotente et ne contient aucun secret : le mot de passe
 * vient de --mot-de-passe ou de la variable APP_DB_ROLE_PASSWORD.
 */
#[AsCommand(
    name: 'app:securite:role-applicatif',
    description: 'Cree ou met a jour le role PostgreSQL non-proprietaire utilise par l application'
)]
final class CreerRoleApplicatifCommand extends Command
{
    /** Tables sur lesquelles le role travaille. */
    private const TABLES = [
        'organisation',
        'membre_organisation',
        'utilisateur',
        'evenement',
        'billet',
        'commande',
        'commande_ligne',
        'ticket_design',
        'log_securite',
        'app_setting',
        'messenger_messages',
    ];

    public function __construct(private Connection $connexion)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Nom du role', 'talchif_app')
            ->addOption(
                'mot-de-passe',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe du role (defaut : variable APP_DB_ROLE_PASSWORD)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = (string) $input->getOption('role');
        if (!preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $role)) {
            $io->error('Nom de role invalide : minuscules, chiffres et tirets bas uniquement.');

            return Command::INVALID;
        }

        $motDePasse = (string) ($input->getOption('mot-de-passe') ?? '');
        if ($motDePasse === '') {
            // $_ENV n'est pas toujours peuple selon variables_order : on
            // interroge aussi l'environnement du processus.
            $motDePasse = (string) ($_ENV['APP_DB_ROLE_PASSWORD'] ?? getenv('APP_DB_ROLE_PASSWORD') ?: '');
        }
        if ($motDePasse === '') {
            $io->error('Mot de passe absent : passez --mot-de-passe ou definissez APP_DB_ROLE_PASSWORD.');

            return Command::INVALID;
        }

        $base = $this->connexion->getDatabase();
        $roleCite = $this->connexion->quoteSingleIdentifier($role);

        try {
            $existe = (bool) $this->connexion->fetchOne(
                'SELECT 1 FROM pg_roles WHERE rolname = ?',
                [$role]
            );

            // Le mot de passe passe par un litteral chaine : CREATE/ALTER ROLE
            // n'accepte pas de parametre lie.
            $motDePasseCite = $this->connexion->quote($motDePasse);

            if ($existe) {
                $this->connexion->executeStatement(sprintf(
                    'ALTER ROLE %s WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %s',
                    $roleCite,
                    $motDePasseCite
                ));
                $io->text(sprintf('Role %s mis a jour.', $role));
            } else {
                $this->connexion->executeStatement(sprintf(
                    'CREATE ROLE %s WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %s',
                    $roleCite,
                    $motDePasseCite
                ));
                $io->text(sprintf('Role %s cree.', $role));
            }

            // Acces a la base et au schema, sans droit de creation.
            $this->connexion->executeStatement(sprintf(
                'GRANT CONNECT ON DATABASE %s TO %s',
                $this->connexion->quoteSingleIdentifier((string) $base),
                $roleCite
            ));
            $this->connexion->executeStatement(sprintf('GRANT USAGE ON SCHEMA public TO %s', $roleCite));

            $absentes = [];
            foreach (self::TABLES as $table) {
                if (!$this->tableExiste($table)) {
                    $absentes[] = $table;
                    continue;
                }

                $this->connexion->executeStatement(sprintf(
                    'GRANT SELECT, INSERT, UPDATE, DELETE ON %s TO %s',
                    $this->connexion->quoteSingleIdentifier($table),
                    $roleCite
                ));
            }

            // Les identites sont en IDENTITY : les sequences implicites suivent
            // les droits de la table. Les sequences explicites, si un jour il y
            // en a, sont couvertes ici.
            $this->connexion->executeStatement(
                sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %s', $roleCite)
            );

            if ($absentes !== []) {
                $io->warning(sprintf(
                    'Tables absentes, droits non accordes : %s. Lancez les migrations puis relancez cette commande.',
                    implode(', ', $absentes)
                ));
            }
        } catch (\Throwable $e) {
            $io->error('Echec : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Role %s pret.', $role));
        $io->section('Etape suivante');
        $io->text([
            'Faites pointer DATABASE_URL sur ce role, par exemple :',
            sprintf('  DATABASE_URL="postgresql://%s:MOT_DE_PASSE@HOTE:5432/%s?serverVersion=16&charset=utf8"', $role, (string) $base),
            '',
            'Gardez le compte proprietaire pour les migrations : ce role ne peut pas modifier le schema.',
        ]);

        return Command::SUCCESS;
    }

    private function tableExiste(string $table): bool
    {
        return (bool) $this->connexion->fetchOne(
            'SELECT 1 FROM pg_tables WHERE schemaname = current_schema() AND tablename = ?',
            [$table]
        );
    }
}
