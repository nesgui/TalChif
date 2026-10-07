<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Repository\CommandeRepository;
use App\Entity\LogSecurite;
use App\Service\Paiement\ContexteConfirmation;
use App\Service\Paiement\ServiceConfirmationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Traite un callback webhook PawaPay.
 *
 * Specificite de ce chemin : traduire la machine a etats de PawaPay en une
 * decision metier. L'emission des billets est deleguee a
 * ServiceConfirmationPaiement, qui garantit l'idempotence des callbacks
 * repetes ou simultanes.
 */
final class ConfirmerDepotPawaPayHandler
{
    /** Statuts non terminaux : on attend un callback ulterieur. */
    private const STATUTS_INTERMEDIAIRES = ['ACCEPTED', 'SUBMITTED', 'ENQUEUED', 'PENDING', 'PROCESSING'];

    /** Statuts terminaux en echec. */
    private const STATUTS_ECHEC = ['FAILED', 'REJECTED', 'CANCELLED', 'EXPIRED'];

    private const STATUT_SUCCES = 'COMPLETED';

    public function __construct(
        private CommandeRepository $commandeRepository,
        private ServiceConfirmationPaiement $serviceConfirmation,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(string $depositId, string $status): void
    {
        $commande = $this->commandeRepository->findByDepositId($depositId);
        if (!$commande) {
            $this->logger->warning('PawaPay callback : commande non trouvee', ['depositId' => $depositId]);

            return;
        }

        $statut = strtoupper($status);
        $journal = ['depositId' => $depositId, 'status' => $statut, 'reference' => $commande->getReference()];

        if (\in_array($statut, self::STATUTS_INTERMEDIAIRES, true)) {
            $this->logger->info('PawaPay callback : statut intermediaire', $journal);

            return;
        }

        if (\in_array($statut, self::STATUTS_ECHEC, true)) {
            $this->rejeter($commande->getReference(), $depositId, $statut);

            return;
        }

        if ($statut !== self::STATUT_SUCCES) {
            $this->logger->warning('PawaPay callback : statut inconnu', $journal);

            return;
        }

        $resultat = $this->serviceConfirmation->confirmer(
            $commande,
            ContexteConfirmation::webhookPawaPay($depositId)
        );

        $this->logger->info(
            $resultat->dejaConfirmee
                ? 'PawaPay callback : commande deja confirmee, callback ignore'
                : 'PawaPay callback : commande confirmee',
            $journal + ['billets' => $resultat->nombreBillets]
        );
    }

    private function rejeter(string $reference, string $depositId, string $statut): void
    {
        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande || !$commande->isProcessing()) {
            $this->logger->info('PawaPay callback : depot non complete, aucune action', [
                'depositId' => $depositId,
                'status' => $statut,
                'reference' => $reference,
            ]);

            return;
        }

        $commande->marquerRejetee();

        $log = new LogSecurite();
        $log->setAction('PAWAPAY_DEPOT_ECHOUE');
        $log->setReferenceCommande($reference);
        $log->setDetails("Depot PawaPay {$depositId} statut : {$statut}");
        $log->setIpAddress('pawapay-webhook');
        $this->entityManager->persist($log);

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        $this->logger->info('PawaPay callback : commande rejetee', [
            'depositId' => $depositId,
            'status' => $statut,
            'reference' => $reference,
        ]);
    }
}
