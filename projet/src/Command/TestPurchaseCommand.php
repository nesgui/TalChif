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
    name: 'app:test-purchase',
    description: 'Test le système d\'achat complet'
)]
class TestPurchaseCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Test du système d\'achat...</info>');

        // Récupérer un client et un organisateur
        $client = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'client1@talchif.td']);
        $organisateur = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'org1@talchif.td']);

        if (!$client) {
            $output->writeln('<error>Client non trouvé. Exécutez d\'abord app:create-test-data</error>');
            return Command::FAILURE;
        }

        // Récupérer quelques événements
        $evenements = $this->entityManager->getRepository(Evenement::class)->findBy(['isActive' => true], [], 3);

        if (empty($evenements)) {
            $output->writeln('<error>Aucun événement trouvé. Exécutez d\'abord app:create-test-events</error>');
            return Command::FAILURE;
        }

        $transactionId = 'TEST_' . uniqid();
        $totalBillets = 0;

        foreach ($evenements as $evenement) {
            $quantite = rand(1, 3);
            
            // Vérifier les places disponibles
            if ($quantite > $evenement->getPlacesRestantes()) {
                $output->writeln("<comment>Pas assez de places pour {$evenement->getNom()} (disponible: {$evenement->getPlacesRestantes()}, demandé: {$quantite})</comment>");
                $quantite = min($quantite, $evenement->getPlacesRestantes());
            }

            if ($quantite <= 0) {
                continue;
            }

            // Créer les billets
            for ($i = 0; $i < $quantite; $i++) {
                $billet = new Billet();
                $billet->setQrCode($this->generateQrCode());
                $billet->setType('SIMPLE');
                $billet->setPrix($evenement->getPrixSimple());
                $billet->setEvenement($evenement);
                $billet->setClient($client);
                $billet->setOrganisateur($organisateur);
                $billet->setTransactionId($transactionId);
                $billet->setStatutPaiement('PAYE');
                $billet->validerPaiement();

                $this->entityManager->persist($billet);
                $totalBillets++;
            }

            // Mettre à jour les places vendues
            $evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);

            $output->writeln("<info>✓ {$quantite} billet(s) créé(s) pour: {$evenement->getNom()}</info>");
        }

        $this->entityManager->flush();

        $output->writeln('<success>Test d\'achat terminé avec succès !</success>');
        $output->writeln("<info>Transaction ID: {$transactionId}</info>");
        $output->writeln("<info>Total billets créés: {$totalBillets}</info>");
        $output->writeln('<info>Client: ' . $client->getFullName() . '</info>');

        // Afficher les URLs pour tester
        $output->writeln('<info>URLs de test:</info>');
        $output->writeln("<info>- Panier: http://127.0.0.1:8000/panier</info>");
        $output->writeln("<info>- Achat: http://127.0.0.1:8000/achat</info>");
        $output->writeln("<info>- Confirmation: http://127.0.0.1:8000/achat/confirmation/{$transactionId}</info>");
        $output->writeln("<info>- Portefeuille: http://127.0.0.1:8000/portefeuille</info>");

        return Command::SUCCESS;
    }

    private function generateQrCode(): string
    {
        return 'BILLET_' . uniqid() . '_' . time();
    }
}
