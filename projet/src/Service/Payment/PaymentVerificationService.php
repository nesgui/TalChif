<?php

namespace App\Service\Payment;

use App\Entity\Commande;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\DoctrineCommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentVerificationService
{
    public function __construct(
        private PawaPayClient $pawaPayClient,
        private DoctrineCommandeRepository $commandeRepository,
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

        $user = $commande->getClient();
        
        // Mettre à jour la balance de l'utilisateur (cashback ou bonus)
        $cashbackAmount = (int) ($commande->getMontantTotal() * 0.01); // 1% de cashback
        $user->addBalance($cashbackAmount);
        
        // Mettre à jour la commande
        $commande->marquerPayee();
        
        // Générer les billets
        $this->generateTickets($commande);
        
        // Sauvegarder
        $this->entityManager->flush();
        
        $this->logger->info('Paiement validé avec succès', [
            'reference' => $commande->getReference(),
            'depositId' => $depositId,
            'montant' => $commande->getMontantTotal(),
            'cashback' => $cashbackAmount,
            'nouvelle_balance' => $user->getBalance()
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
            for ($i = 0; $i < $ligne->getQuantite(); $i++) {
                $billet = new \App\Entity\Billet();
                $billet->setQrCode($this->generateQrCode());
                $billet->setType($ligne->getTypeBillet());
                $billet->setPrix($ligne->getPrixUnitaire());
                $billet->setClient($commande->getClient());
                $billet->setEvenement($ligne->getEvenement());
                $billet->setStatutPaiement('PAYE');
                $billet->setTransactionId($commande->getReference());
                
                $this->entityManager->persist($billet);
                
                // Mettre à jour les places vendues de l'événement
                $ligne->getEvenement()->incrementerPlacesVendues();
            }
        }
    }

    private function generateQrCode(): string
    {
        return 'BILLET_' . uniqid() . '_' . time();
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
