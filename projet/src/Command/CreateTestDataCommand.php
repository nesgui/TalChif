<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-data',
    description: 'Crée des données de test pour l\'application'
)]
class CreateTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Création des données de test...</info>');

        // Créer un admin
        $admin = new User();
        $admin->setEmail('admin@osea.td');
        $admin->setNom('Admin');
        $admin->setPrenom('System');
        $admin->setTelephone('23500000000');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRole('ADMIN');
        $admin->setIsVerified(true);
        $this->entityManager->persist($admin);

        // Créer un organisateur
        $org = new User();
        $org->setEmail('org1@osea.td');
        $org->setNom('Ngarté');
        $org->setPrenom('Marie');
        $org->setTelephone('23561234567');
        $org->setPassword($this->passwordHasher->hashPassword($org, 'org123'));
        $org->setRole('ORGANISATEUR');
        $org->setIsVerified(true);
        $this->entityManager->persist($org);

        // Créer un client
        $client = new User();
        $client->setEmail('client1@osea.td');
        $client->setNom('Hassane');
        $client->setPrenom('Ali');
        $client->setTelephone('23561111111');
        $client->setPassword($this->passwordHasher->hashPassword($client, 'client123'));
        $client->setRole('CLIENT');
        $client->setIsVerified(true);
        $this->entityManager->persist($client);

        $this->entityManager->flush();

        $output->writeln('<success>Données de test créées avec succès !</success>');
        $output->writeln('');
        $output->writeln('<info>Comptes créés :</info>');
        $output->writeln('  Admin: admin@osea.td / admin123');
        $output->writeln('  Organisateur: org1@osea.td / org123');
        $output->writeln('  Client: client1@osea.td / client123');

        return Command::SUCCESS;
    }
}
