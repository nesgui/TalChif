<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Liste les utilisateurs et leurs roles.
 *
 * Passe par l'ORM et non par du SQL brut : « user » est un mot reserve
 * PostgreSQL, Doctrine se charge de l'echappement selon la plateforme.
 */
#[AsCommand(
    name: 'app:check-users',
    description: 'Verifie les utilisateurs et leurs roles'
)]
final class CheckUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $utilisateurs = $this->userRepository->findBy([], ['id' => 'ASC']);
        if ($utilisateurs === []) {
            $io->warning('Aucun utilisateur en base.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Email', 'Role metier', 'Roles Symfony', 'Actif'],
            array_map(static fn (User $u): array => [
                (string) $u->getId(),
                (string) $u->getEmail(),
                $u->getRole(),
                implode(', ', $u->getRoles()),
                $u->isActif() ? 'oui' : 'non',
            ], $utilisateurs)
        );

        return Command::SUCCESS;
    }
}
