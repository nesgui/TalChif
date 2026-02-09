<?php

namespace App\Command;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-past-ticket',
    description: 'Crée un billet pour un événement passé'
)]
class CreatePastTicketCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Création d\'un billet passé...</info>');

        // Récupérer un client et un événement
        $client = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'client1@osea.td']);
        $evenement = $this->entityManager->getRepository(Evenement::class)->findOneBy(['isActive' => true]);
        
        if (!$client || !$evenement) {
            $output->writeln('<error>Client ou événement non trouvé. Exécutez d\'abord les commandes de test.</error>');
            return Command::FAILURE;
        }

        // Créer un billet avec une date passée
        $billet = new Billet();
        $billet->setQrCode('TEST_PASSED_' . uniqid());
        $billet->setType('SIMPLE');
        $billet->setPrix(3000);
        $billet->setEvenement($evenement);
        $billet->setClient($client);
        $billet->setOrganisateur($evenement->getOrganisateur());
        $billet->setTransactionId('TEST_PASSED_' . uniqid());
        $billet->setStatutPaiement('PAYE');
        $billet->validerPaiement();

        // Modifier la date de l'événement pour le rendre passé (hier)
        $pastDate = new \DateTimeImmutable('yesterday');
        $pastDate = $pastDate->setTime(18, 0, 0); // 18h00 hier
        $evenement->setDateEvenement($pastDate);

        $this->entityManager->persist($billet);
        $this->entityManager->flush();

        $output->writeln('<success>✅ Billet passé créé avec succès !</success>');
        $output->writeln("<info>QR Code: {$billet->getQrCode()}</info>");
        $output->writeln("<info>Date événement: {$evenement->getDateEvenement()->format('d/m/Y H:i')}</info>");
        $output->writeln("<info>Client: {$client->getFullName()}</info>");
        $output->writeln("<info>URL: http://127.0.0.1:8000/mes-billets/passes</info>");

        return Command::SUCCESS;
    }
}
