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
    name: 'app:test-payment',
    description: 'Test rapide du système de paiement Mobile Money'
)]
class TestPaymentCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🧪 Test du système de paiement Mobile Money...</info>');

        // Récupérer un client
        $client = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'client1@talchif.td']);
        
        if (!$client) {
            $output->writeln('<error>❌ Client non trouvé. Exécutez d\'abord: php bin/console app:create-test-data</error>');
            return Command::FAILURE;
        }

        // Récupérer un événement actif
        $evenement = $this->entityManager->getRepository(Evenement::class)->findOneBy(['isActive' => true]);
        
        if (!$evenement) {
            $output->writeln('<error>❌ Aucun événement trouvé. Exécutez d\'abord: php bin/console app:create-test-events</error>');
            return Command::FAILURE;
        }

        // Simulation d'un paiement
        $output->writeln('<info>📱 Simulation du paiement...</info>');
        $output->writeln("<info>   Client: {$client->getFullName()}</info>");
        $output->writeln("<info>   Événement: {$evenement->getNom()}</info>");
        $output->writeln("<info>   Prix: {$evenement->getPrixSimple()} XAF</info>");
        
        // Simuler validation téléphone
        $telephone = '235 66 01 02 03';
        $methode = 'momo';
        $output->writeln("<info>   Téléphone: {$telephone}</info>");
        $output->writeln("<info>   Méthode: {$methode}</info>");
        
        // Simuler délai de traitement
        $output->writeln('<info>⏳ Vérification du solde...</info>');
        sleep(1);
        
        // Créer le billet
        $transactionId = 'TEST_MOMO_' . uniqid();
        $billet = new Billet();
        $billet->setQrCode($this->generateQrCode());
        $billet->setType('SIMPLE');
        $billet->setPrix($evenement->getPrixSimple());
        $billet->setEvenement($evenement);
        $billet->setClient($client);
        $billet->setOrganisateur($evenement->getOrganisateur());
        $billet->setTransactionId($transactionId);
        $billet->setStatutPaiement('PAYE');
        $billet->validerPaiement();

        $this->entityManager->persist($billet);
        
        // Mettre à jour les places
        $evenement->setPlacesVendues($evenement->getPlacesVendues() + 1);
        
        $this->entityManager->flush();

        $output->writeln('<success>✅ Paiement simulé avec succès !</success>');
        $output->writeln("<info>   Transaction ID: {$transactionId}</info>");
        $output->writeln("<info>   QR Code: {$billet->getQrCode()}</info>");
        $output->writeln("<info>   Places restantes: {$evenement->getPlacesRestantes()}</info>");
        
        $output->writeln('<info>📱 SMS de confirmation simulé...</info>');
        $output->writeln("<info>   Destinataire: {$telephone}</info>");
        $output->writeln("<info>   Message: TalChif - Paiement de {$evenement->getPrixSimple()} XAF confirmé. Billet: {$billet->getQrCode()}</info>");

        $output->writeln('<info>🌐 URLs de test:</info>');
        $output->writeln("<info>   - Panier: http://127.0.0.1:8000/panier</info>");
        $output->writeln("<info>   - Achat: http://127.0.0.1:8000/achat</info>");
        $output->writeln("<info>   - Billet: http://127.0.0.1:8000/achat/billet/{$billet->getQrCode()}</info>");
        $output->writeln("<info>   - Confirmation: http://127.0.0.1:8000/achat/confirmation/{$transactionId}</info>");

        return Command::SUCCESS;
    }

    private function generateQrCode(): string
    {
        return 'TEST_' . uniqid() . '_' . time();
    }
}
