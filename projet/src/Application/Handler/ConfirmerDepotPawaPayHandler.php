<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Domain\Repository\CommandeRepositoryInterface;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Domain\Repository\BilletRepositoryInterface;
use App\Domain\Exception\PlacesInsuffisantesException;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\LogSecurite;
use App\Service\Ticket\QrCodeGeneratorService;
use App\Service\Notification\BilletEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler pour confirmer un dépôt PawaPay via webhook.
 * Génère automatiquement les billets et valide la commande.
 */
final class ConfirmerDepotPawaPayHandler
{
    public function __construct(
        private CommandeRepositoryInterface $commandeRepository,
        private EvenementRepositoryInterface $evenementRepository,
        private BilletRepositoryInterface $billetRepository,
        private QrCodeGeneratorService $qrCodeGenerator,
        private BilletEmailService $billetEmailService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Traite un callback webhook PawaPay.
     */
    public function handle(string $depositId, string $status): void
    {
        $this->logger->info('PawaPay webhook reçu', [
            'depositId' => $depositId,
            'status' => $status,
        ]);

        // Trouver la commande via depositId
        $commande = $this->commandeRepository->findByDepositId($depositId);
        if (!$commande) {
            $this->logger->warning('PawaPay callback: commande non trouvée', [
                'depositId' => $depositId,
            ]);
            return;
        }

        // Forcer le rechargement depuis la BDD pour éviter les problèmes de cache
        $this->entityManager->clear();
        $commande = $this->commandeRepository->findByDepositId($depositId);

        // Normaliser le statut PawaPay
        $statusPawaPay = strtoupper($status);

        // Statuts intermediaires: ne rien faire, on attend le statut final
        $intermediateStatuses = ['ACCEPTED', 'SUBMITTED', 'ENQUEUED', 'PENDING', 'PROCESSING'];
        if (in_array($statusPawaPay, $intermediateStatuses, true)) {
            $this->logger->info('PawaPay callback: statut intermediaire', [
                'depositId' => $depositId,
                'status' => $statusPawaPay,
                'reference' => $commande->getReference(),
            ]);
            return;
        }

        // Statuts finaux en echec -> rejeter la commande si elle est en cours
        $failedStatuses = ['FAILED', 'REJECTED', 'CANCELLED', 'EXPIRED'];
        if (in_array($statusPawaPay, $failedStatuses, true)) {
            $this->logger->info('PawaPay callback: dépôt non complété', [
                'depositId' => $depositId,
                'status' => $statusPawaPay,
                'reference' => $commande->getReference(),
            ]);

            if ($commande->isProcessing()) {
                $commande->marquerRejetee();
                $this->entityManager->persist($commande);
                $this->entityManager->flush();

                $this->loggerAction('PAWAPAY_DEPOT_ECHOUE', $commande->getReference(),
                    "Dépôt PawaPay {$depositId} statut: {$statusPawaPay}");
            }
            return;
        }

        // Statut inconnu -> ignorer sans modifier la commande
        if ($statusPawaPay !== 'COMPLETED') {
            $this->logger->warning('PawaPay callback: statut inconnu', [
                'depositId' => $depositId,
                'status' => $statusPawaPay,
                'reference' => $commande->getReference(),
            ]);
            return;
        }

        // Dépôt complété → générer les billets
        $this->logger->info('PawaPay callback: vérification validabilité', [
            'depositId' => $depositId,
            'reference' => $commande->getReference(),
            'statut' => $commande->getStatut(),
            'isPending' => $commande->isPending(),
            'isProcessing' => $commande->isProcessing(),
            'estExpiree' => $commande->estExpiree(),
            'dateExpiration' => $commande->getDateExpiration()?->format('Y-m-d H:i:s'),
        ]);

        if (!$commande->peutEtreValidee()) {
            $this->logger->warning('PawaPay callback: commande non validable', [
                'depositId' => $depositId,
                'reference' => $commande->getReference(),
                'statut' => $commande->getStatut(),
            ]);
            return;
        }

        $this->entityManager->beginTransaction();
        try {
            // Générer les billets
            foreach ($commande->getLignes() as $ligne) {
                $evenement = $ligne->getEvenement();

                // Verrouiller l'événement
                $evenementLocked = $this->evenementRepository->findByIdWithLock($evenement->getId());
                if (!$evenementLocked) {
                    throw new \RuntimeException("Événement introuvable.");
                }

                $quantite = $ligne->getQuantite();
                if ($quantite > $evenementLocked->getPlacesRestantes()) {
                    throw new PlacesInsuffisantesException(
                        "Plus assez de places pour {$evenementLocked->getNom()}."
                    );
                }

                for ($i = 0; $i < $quantite; $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->qrCodeGenerator->generer());
                    $billet->setType($ligne->getTypeBillet());
                    $billet->setPrix($ligne->getPrixUnitaire());
                    $billet->setEvenement($evenementLocked);
                    $billet->setClient($commande->getClient());
                    $billet->setOrganisateur($evenementLocked->getOrganisateur());
                    $billet->setTransactionId($commande->getReference());
                    $billet->setStatutPaiement('PAYE');
                    $billet->validerPaiement();

                    $this->entityManager->persist($billet);
                }

                // Réserver les places
                $evenementLocked->reserverPlaces($quantite);
                $this->entityManager->persist($evenementLocked);
            }

            // Marquer la commande comme payée (pas de validateur humain)
            $commande->marquerPayee(null);
            $this->entityManager->persist($commande);

            // Mettre à jour la balance de l'utilisateur (cashback 1%)
            $user = $commande->getClient();
            $cashbackAmount = (int) ($commande->getMontantTotal() * 0.01);
            $user->addBalance($cashbackAmount);
            $this->entityManager->persist($user);

            $this->loggerAction('PAWAPAY_DEPOT_CONFIRME', $commande->getReference(),
                "Dépôt PawaPay {$depositId} confirmé automatiquement. Billets générés. Cashback: {$cashbackAmount} FCFA");

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Envoyer l'email de confirmation
            try {
                $this->billetEmailService->envoyerConfirmationAchat($commande);
            } catch (\Throwable $e) {
                // Ne pas faire échouer la commande si l'email plante
                $this->logger->warning('Email confirmation non envoyé', [
                    'reference' => $commande->getReference(),
                    'error' => $e->getMessage(),
                ]);
            }
            
            $this->logger->info('PawaPay: balance mise à jour', [
                'reference' => $commande->getReference(),
                'cashback' => $cashbackAmount,
                'nouvelle_balance' => $user->getBalance()
            ]);

            $this->logger->info('PawaPay callback: commande validée', [
                'depositId' => $depositId,
                'reference' => $commande->getReference(),
                'nbBillets' => count($commande->getLignes()),
            ]);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('PawaPay webhook: erreur traitement', [
                'depositId' => $depositId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    private function loggerAction(string $action, string $reference, string $details): void
    {
        $log = new LogSecurite();
        $log->setAction($action);
        $log->setReferenceCommande($reference);
        $log->setDetails($details);
        $log->setIpAddress('pawapay-webhook');
        $this->entityManager->persist($log);
    }
}
