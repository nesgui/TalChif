<?php

namespace App\Command;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-event-billets',
    description: 'Test le système de gestion des billets dynamiques'
)]
class TestEventBilletsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🎫 Test du système de gestion des billets dynamiques...</info>');

        // Récupérer un événement existant
        $evenement = $this->entityManager->getRepository(Evenement::class)->findOneBy(['isActive' => true]);
        
        if (!$evenement) {
            $output->writeln('<error>❌ Aucun événement trouvé. Exécutez d\'abord: php bin/console app:create-test-events</error>');
            return Command::FAILURE;
        }

        // Créer des billets de différents types pour tester
        $client = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'client1@osea.td']);
        
        if (!$client) {
            $output->writeln('<error>❌ Client non trouvé. Exécutez d\'abord: php bin/console app:create-test-data</error>');
            return Command::FAILURE;
        }

        $types = [
            'Simple' => ['prix' => 5000, 'quantite' => 5],
            'VIP' => ['prix' => 15000, 'quantite' => 2],
        ];

        $createdBillets = [];
        
        foreach ($types as $type => $info) {
            for ($i = 0; $i < $info['quantite']; $i++) {
                $billet = new \App\Entity\Billet();
                $billet->setQrCode('TEST_' . strtoupper($type) . '_' . uniqid());
                $billet->setType($type);
                $billet->setPrix($info['prix']);
                $billet->setEvenement($evenement);
                $billet->setClient($client);
                $billet->setOrganisateur($evenement->getOrganisateur());
                $billet->setTransactionId('TEST_' . uniqid());
                $billet->setStatutPaiement('PAYE');
                $billet->setIsValide(true);
                $billet->validerPaiement();

                $this->entityManager->persist($billet);
                $createdBillets[] = $billet;
            }
        }

        // Mettre à jour les places vendues
        $evenement->setPlacesVendues(count($createdBillets));
        $this->entityManager->flush();

        $output->writeln('<success>✅ Billets de test créés avec succès !</success>');
        
        foreach ($createdBillets as $billet) {
            $output->writeln("<info>• {$billet->getType()} - {$billet->getPrix()} XAF - {$billet->getQrCode()}</info>");
        }

        $output->writeln("<info>URL de test: http://127.0.0.1:8000/evenements/{$evenement->getSlug()}-{$evenement->getId()}/billets");
        $output->writeln('<info>Types de billets: Simple (5 000 XAF) et VIP (15 000 XAF)');

        return Command::SUCCESS;
    }
}
