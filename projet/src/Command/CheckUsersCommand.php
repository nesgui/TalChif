<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:check-users',
    description: 'Vérifie les utilisateurs et leurs rôles'
)]
class CheckUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT id, email, role, roles FROM user";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        
        $users = $result->fetchAllAssociative();
        
        $output->writeln('<info>Utilisateurs dans la base de données :</info>');
        foreach ($users as $user) {
            $output->writeln(sprintf(
                'ID: %d | Email: %s | Rôle: %s | Roles: %s',
                $user['id'],
                $user['email'],
                $user['role'],
                $user['roles']
            ));
        }
        
        return Command::SUCCESS;
    }
}
