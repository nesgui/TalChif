<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Repository\CommandeRepository;
use App\Entity\LogSecurite;
use App\Service\Paiement\ContexteConfirmation;
use App\Service\Paiement\ServiceConfirmationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Rattrapage des commandes restees en traitement.
 *
 * Specificite de ce chemin : interroger l'API PawaPay pour les commandes dont
 * aucun webhook n'est arrive. L'emission des billets est deleguee a
 * ServiceConfirmationPaiement, ce qui rend le rattrapage sans risque meme si
 * le webhook finit par arriver (idempotence).
 */
final class PaymentVerificationService
{
    private const STATUT_SUCCES = 'COMPLETED';
    private const STATUTS_ECHEC = ['FAILED', 'REJECTED'];

    public function __construct(
        private PawaPayClient $pawaPayClient,
        private CommandeRepository $commandeRepository,
        private ServiceConfirmationPaiement $serviceConfirmation,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function verifyAndUpdateBalance(string $depositId): bool
    {
        $commande = $this->commandeRepository->findByDepositId($depositId);
        if (!$commande) {
            $this->logger->warning('Commande non trouvee pour ce depositId', ['depositId' => $depositId]);

            return false;
        }

        try {
            $statut = strtoupper((string) $this->pawaPayClient->verifierStatutDepot($depositId));
        } catch (\Throwable $e) {
            $this->logger->error('Interrogation PawaPay impossible', [
                'depositId' => $depositId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($statut === self::STATUT_SUCCES) {
            try {
                $resultat = $this->serviceConfirmation->confirmer(
                    $commande,
                    ContexteConfirmation::sondagePawaPay($depositId)
                );
            } catch (\Throwable $e) {
                $this->logger->error('Confirmation par sondage en echec', [
                    'depositId' => $depositId,
                    'reference' => $commande->getReference(),
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            return !$resultat->dejaConfirmee;
        }

        if (\in_array($statut, self::STATUTS_ECHEC, true)) {
            return $this->rejeter($depositId);
        }

        $this->logger->info('Paiement encore en cours', [
            'depositId' => $depositId,
            'reference' => $commande->getReference(),
            'status' => $statut,
        ]);

        return false;
    }

    /**
     * Parcourt les commandes en traitement et tente de les resoudre.
     *
     * @return list<array{reference: string, depositId: string, success: bool}>
     */
    public function checkPendingPayments(): array
    {
        $resultats = [];

        foreach ($this->commandeRepository->findProcessingWithDepositId() as $commande) {
            $depositId = (string) $commande->getDepositId();

            $resultats[] = [
                'reference' => (string) $commande->getReference(),
                'depositId' => $depositId,
                'success' => $this->verifyAndUpdateBalance($depositId),
            ];
        }

        return $resultats;
    }

    private function rejeter(string $depositId): bool
    {
        $commande = $this->commandeRepository->findByDepositId($depositId);
        if (!$commande || $commande->isRejected()) {
            return false;
        }

        if (!$commande->isPending() && !$commande->isProcessing()) {
            return false;
        }

        $commande->marquerRejetee();

        $log = new LogSecurite();
        $log->setAction('PAWAPAY_DEPOT_ECHOUE_SONDAGE');
        $log->setReferenceCommande((string) $commande->getReference());
        $log->setDetails("Depot PawaPay {$depositId} en echec, detecte par verification periodique");
        $log->setIpAddress('pawapay-polling');
        $this->entityManager->persist($log);

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        $this->logger->warning('Paiement rejete', [
            'depositId' => $depositId,
            'reference' => $commande->getReference(),
        ]);

        return true;
    }
}
