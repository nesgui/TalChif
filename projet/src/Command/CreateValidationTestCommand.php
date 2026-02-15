<?php

namespace App\Command;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\BilletRepository;
use App\Repository\EvenementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-validation-test',
    description: 'Crée des billets de test pour la validation'
)]
class CreateValidationTestCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EvenementRepository $evenementRepository,
        private BilletRepository $billetRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🎫 Création de billets de test pour la validation...</info>');

        // Récupérer ou créer un organisateur
        $organisateur = $this->userRepository->findOneBy(['email' => 'organisateur@talchif.td']);
        
        if (!$organisateur) {
            $output->writeln('<error>❌ Organisateur non trouvé. Exécutez d\'abord: php bin/console app:create-test-data</error>');
            return Command::FAILURE;
        }

        // Créer un événement de test pour aujourd'hui
        $evenement = $this->evenementRepository->findOneBy(['nom' => 'Concert de Test Validation']);
        
        if (!$evenement) {
            $evenement = new Evenement();
            $evenement->setNom('Concert de Test Validation');
            $evenement->setDescription('Événement de test pour le système de validation');
            $evenement->setDateEvenement(new \DateTimeImmutable('+2 hours')); // Dans 2 heures
            $evenement->setLieu('Salle de Test TalChif');
            $evenement->setVille('N\'Djamena');
            $evenement->setPrixSimple(5000);
            $evenement->setPrixVip(15000);
            $evenement->setPlacesDisponibles(200);
            $evenement->setPlacesVendues(10);
            $evenement->setAffichePrincipale('/images/evenements/concert.svg');
            $evenement->setOrganisateur($organisateur);
            $evenement->setIsActive(true);
            
            $this->entityManager->persist($evenement);
            $this->entityManager->flush();
            
            $output->writeln('<info>✅ Événement de test créé</info>');
        }

        // Créer des clients de test
        $clients = [
            ['email' => 'client1@validation.test', 'prenom' => 'Marie', 'nom' => 'Dupont'],
            ['email' => 'client2@validation.test', 'prenom' => 'Jean', 'nom' => 'Martin'],
            ['email' => 'client3@validation.test', 'prenom' => 'Alice', 'nom' => 'Bob'],
        ];

        $createdClients = [];
        foreach ($clients as $clientData) {
            $client = $this->userRepository->findOneBy(['email' => $clientData['email']]);
            
            if (!$client) {
                $client = new User();
                $client->setEmail($clientData['email']);
                $client->setPrenom($clientData['prenom']);
                $client->setNom($clientData['nom']);
                $client->setPassword('password123');
                $client->setRoles(['ROLE_CLIENT']);
                $client->setIsVerified(true);
                
                $this->entityManager->persist($client);
                $createdClients[] = $client;
            } else {
                $createdClients[] = $client;
            }
        }

        $this->entityManager->flush();

        // Créer des billets de test avec différents statuts
        $testBillets = [
            [
                'qrCode' => 'VALID_SIMPLE_OK_001',
                'type' => 'Simple',
                'prix' => 5000,
                'statut' => 'PAYE',
                'utilise' => false,
                'clientEmail' => 'client1@validation.test'
            ],
            [
                'qrCode' => 'VALID_VIP_OK_002',
                'type' => 'VIP',
                'prix' => 15000,
                'statut' => 'PAYE',
                'utilise' => false,
                'clientEmail' => 'client2@validation.test'
            ],
            [
                'qrCode' => 'VALID_USED_003',
                'type' => 'Simple',
                'prix' => 5000,
                'statut' => 'PAYE',
                'utilise' => true,
                'dateUtilisation' => new \DateTimeImmutable('-1 hour'),
                'clientEmail' => 'client3@validation.test'
            ],
            [
                'qrCode' => 'VALID_NOT_PAID_004',
                'type' => 'Simple',
                'prix' => 5000,
                'statut' => 'EN_ATTENTE',
                'utilise' => false,
                'clientEmail' => 'client1@validation.test'
            ],
            [
                'qrCode' => 'VALID_WRONG_DATE_005',
                'type' => 'VIP',
                'prix' => 15000,
                'statut' => 'PAYE',
                'utilise' => false,
                'clientEmail' => 'client2@validation.test',
                'evenementDate' => new \DateTimeImmutable('-1 day') // Hier
            ],
        ];

        $createdBillets = [];
        foreach ($testBillets as $billetData) {
            // Vérifier si le billet existe déjà
            $existingBillet = $this->billetRepository->findOneBy(['qrCode' => $billetData['qrCode']]);
            
            if (!$existingBillet) {
                $billet = new Billet();
                $billet->setQrCode($billetData['qrCode']);
                $billet->setType($billetData['type']);
                $billet->setPrix($billetData['prix']);
                $billet->setStatutPaiement($billetData['statut']);
                $billet->setUtilise($billetData['utilise']);
                
                // Utiliser l'événement créé ou un événement spécifique
                $eventToUse = $evenement;
                if (isset($billetData['evenementDate'])) {
                    // Créer un événement avec une date différente pour les tests
                    $specialEvent = new Evenement();
                    $specialEvent->setNom('Événement Spécial Test');
                    $specialEvent->setDescription('Événement avec date différente');
                    $specialEvent->setDateEvenement($billetData['evenementDate']);
                    $specialEvent->setLieu('Lieu Spécial');
                    $specialEvent->setVille('N\'Djamena');
                    $specialEvent->setPrixSimple(5000);
                    $specialEvent->setPrixVip(15000);
                    $specialEvent->setPlacesDisponibles(100);
                    $specialEvent->setPlacesVendues(5);
                    $specialEvent->setAffichePrincipale('/images/evenements/match.svg');
                    $specialEvent->setOrganisateur($organisateur);
                    $specialEvent->setIsActive(true);
                    
                    $this->entityManager->persist($specialEvent);
                    $this->entityManager->flush();
                    
                    $eventToUse = $specialEvent;
                }
                
                $billet->setEvenement($eventToUse);
                $billet->setOrganisateur($organisateur);
                
                // Trouver le client correspondant
                $client = $this->userRepository->findOneBy(['email' => $billetData['clientEmail']]);
                if ($client) {
                    $billet->setClient($client);
                }
                
                $billet->setTransactionId('TEST_' . uniqid());
                
                if ($billetData['utilise'] && isset($billetData['dateUtilisation'])) {
                    $billet->setDateUtilisation($billetData['dateUtilisation']);
                    $billet->setValidePar($organisateur);
                }
                
                $this->entityManager->persist($billet);
                $createdBillets[] = $billet;
            }
        }

        $this->entityManager->flush();

        $output->writeln('<success>✅ Billets de test créés avec succès !</success>');
        
        foreach ($createdBillets as $billet) {
            $status = $billet->isUtilise() ? 'Déjà utilisé' : 'Non utilisé';
            $output->writeln("<info>• {$billet->getQrCode()} - {$billet->getType()} - {$billet->getPrix()} XAF - {$status}</info>");
        }

        $output->writeln('');
        $output->writeln('<comment>📋 Codes QR de test pour la validation:</comment>');
        $output->writeln('<comment>VALID_SIMPLE_OK_001 (Simple, Payé, Non utilisé)</comment>');
        $output->writeln('<comment>VALID_VIP_OK_002 (VIP, Payé, Non utilisé)</comment>');
        $output->writeln('<comment>VALID_USED_003 (Simple, Payé, Déjà utilisé)</comment>');
        $output->writeln('<comment>VALID_NOT_PAID_004 (Simple, Non payé)</comment>');
        $output->writeln('<comment>VALID_WRONG_DATE_005 (VIP, Payé, Date passée)</comment>');
        
        $output->writeln('');
        $output->writeln('<info>URL de validation: http://127.0.0.1:8000/validation</info>');
        $output->writeln('<info>URL historique: http://127.0.0.1:8000/validation/historique</info>');

        return Command::SUCCESS;
    }
}
