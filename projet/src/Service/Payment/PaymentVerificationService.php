<?php

namespace App\Service\Payment;

use App\Entity\Commande;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\DoctrineCommandeRepository;
use App\Service\Ticket\QrCodeGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentVerificationService
{
    public function __construct(
        private PawaPayClient $pawaPayClient,
        private DoctrineCommandeRepository $commandeRepository,
        private QrCodeGeneratorService $qrCodeGenerator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function verifyAndUpdateBalance(string $depositId): bool
    {
        try {
            // Récupérer la commande via le depositId
            $commande = $this->commandeRepository->findByDepositId($depositId);
            
            if (!$commande) {
                $this->logger->warning('Commande non trouvée pour le depositId', ['depositId' => $depositId]);
                return false;
            }

            // Vérifier le statut du paiement via PawaPay API
            $status = $this->pawaPayClient->verifierStatutDepot($depositId);
            
            if ($status === 'COMPLETED') {
                return $this->processSuccessfulPayment($commande, $depositId);
            } elseif ($status === 'FAILED' || $status === 'REJECTED') {
                return $this->processFailedPayment($commande, $depositId);
            }

            // Statut encore en cours (PROCESSING, PENDING)
            $this->logger->info('Paiement encore en cours', [
                'depositId' => $depositId,
                'reference' => $commande->getReference(),
                'status' => $status
            ]);
            
            return false;
            
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la vérification du paiement', [
                'depositId' => $depositId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    private function processSuccessfulPayment(Commande $commande, string $depositId): bool
    {
        if ($commande->getStatut() === Commande::STATUT_PAID) {
            $this->logger->info('Commande déjà validée', ['reference' => $commande->getReference()]);
            return true;
        }

        // 1. Marquer la commande payée
        $commande->marquerPayee();

        // 2. Générer les billets EN PREMIER
        $this->generateTickets($commande);

        // 3. Créditer le cashback SEULEMENT après succès de la génération
        $user = $commande->getClient();
        $cashbackAmount = (int) ($commande->getMontantTotal() * 0.01);
        $user->addBalance($cashbackAmount);

        // 4. Sauvegarder tout en une seule transaction
        $this->entityManager->flush();

        $this->logger->info('Paiement validé avec succès', [
            'reference' => $commande->getReference(),
            'depositId' => $depositId,
            'cashback' => $cashbackAmount,
        ]);

        return true;
    }

    private function processFailedPayment(Commande $commande, string $depositId): bool
    {
        if ($commande->getStatut() === Commande::STATUT_REJECTED) {
            $this->logger->info('Commande déjà rejetée', ['reference' => $commande->getReference()]);
            return true;
        }

        $commande->marquerRejetee();
        $this->entityManager->flush();
        
        $this->logger->warning('Paiement rejeté', [
            'reference' => $commande->getReference(),
            'depositId' => $depositId,
            'montant' => $commande->getMontantTotal()
        ]);
        
        return true;
    }

    private function generateTickets(Commande $commande): void
    {
        foreach ($commande->getLignes() as $ligne) {
            $evenement = $ligne->getEvenement();
            
            // Verrouiller l'événement pour éviter les race conditions
            $evenementLocked = $this->entityManager->find(
                Evenement::class,
                $evenement->getId(),
                \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE
            );
            
            if (!$evenementLocked) {
                throw new \RuntimeException("Événement introuvable.");
            }
            
            $quantite = $ligne->getQuantite();
            
            // Vérifier à nouveau les places avec le verrou
            if ($quantite > $evenementLocked->getPlacesRestantes()) {
                throw new \App\Domain\Exception\PlacesInsuffisantesException(
                    "Plus assez de places disponibles pour « {$evenementLocked->getNom()} »."
                );
            }
            
            for ($i = 0; $i < $quantite; $i++) {
                $billet = new \App\Entity\Billet();
                $billet->setQrCode($this->qrCodeGenerator->generer());
                $billet->setType($ligne->getTypeBillet());
                $billet->setPrix($ligne->getPrixUnitaire());
                $billet->setClient($commande->getClient());
                $billet->setEvenement($evenementLocked);
                $billet->setStatutPaiement('PAYE');
                $billet->setTransactionId($commande->getReference());
                
                $this->entityManager->persist($billet);
            }
            
            // Mettre à jour les places vendues de l'événement
            $evenementLocked->reserverPlaces($quantite);
        }
    }


    public function checkPendingPayments(): array
    {
        $results = [];
        
        // Récupérer toutes les commandes en statut Processing avec un depositId
        $commandesProcessing = $this->entityManager->getRepository(Commande::class)->findBy([
            'statut' => Commande::STATUT_PROCESSING
        ]);

        foreach ($commandesProcessing as $commande) {
            if ($commande->getDepositId()) {
                $success = $this->verifyAndUpdateBalance($commande->getDepositId());
                $results[] = [
                    'reference' => $commande->getReference(),
                    'depositId' => $commande->getDepositId(),
                    'success' => $success
                ];
            }
        }

        return $results;
    }
}
