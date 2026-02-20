<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée (ou met à jour) un compte ADMIN dans la base courante (SQLite dev par défaut).'
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email du compte admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (sera hashé). Si absent, sera demandé en interactif.')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom complet', 'Admin')
            ->addOption('telephone', null, InputOption::VALUE_REQUIRED, 'Téléphone', '000000000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $plainPassword = (string) ($input->getArgument('password') ?? '');
        $nom = (string) $input->getOption('nom');
        $telephone = (string) $input->getOption('telephone');

        if ($plainPassword === '') {
            $helper = $this->getHelper('question');
            $question = new Question('Mot de passe admin: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $answer = $helper->ask($input, $output, $question);
            $plainPassword = (string) ($answer ?? '');
        }

        if ($plainPassword === '') {
            $output->writeln('<error>Mot de passe requis.</error>');
            return Command::FAILURE;
        }

        /** @var User|null $existing */
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        $user = $existing ?? new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setTelephone($telephone);
        $user->setActif(true);
        $user->setIsVerified(true);
        $user->setRole('ADMIN');
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        if (!$existing) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        if ($existing) {
            $output->writeln('OK: compte admin mis à jour: ' . $email);
        } else {
            $output->writeln('OK: compte admin créé: ' . $email);
        }

        return Command::SUCCESS;
    }
}
