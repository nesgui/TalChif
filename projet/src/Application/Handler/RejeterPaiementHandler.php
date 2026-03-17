<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\RejeterPaiementCommand;
use App\Domain\Repository\CommandeRepositoryInterface;
use App\Entity\LogSecurite;
use App\Repository\LogSecuriteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handler pour rejeter un paiement Mobile Money.
 */
final class RejeterPaiementHandler
{
    public function __construct(
        private CommandeRepositoryInterface $commandeRepository,
        private UserRepository $userRepository,
        private LogSecuriteRepository $logSecuriteRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public function handle(RejeterPaiementCommand $command): void
    {
        $commande = $this->commandeRepository->findByReference($command->referenceCommande);
        if (!$commande) {
            throw new \RuntimeException("Commande {$command->referenceCommande} introuvable.");
        }

        if (!$commande->isPending()) {
            throw new \RuntimeException(
                "La commande {$command->referenceCommande} n'est pas en attente."
            );
        }

        $this->entityManager->beginTransaction();
        try {
            $validateur = $this->userRepository->find($command->validateurId);

            $commande->marquerRejetee($validateur);
            $this->commandeRepository->save($commande);

            // Logger l'action
            $this->loggerAction(
                'REJET_PAIEMENT',
                $commande->getReference(),
                "Paiement rejeté par admin ID {$command->validateurId}. Raison: {$command->raison}"
            );

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $this->loggerAction(
                'REJET_PAIEMENT_ECHEC',
                $commande->getReference(),
                "Échec rejet : {$e->getMessage()}"
            );

            throw $e;
        }
    }

    private function loggerAction(string $action, string $reference, string $details): void
    {
        $log = new LogSecurite();
        $log->setAction($action);
        $log->setReferenceCommande($reference);
        $log->setDetails($details);

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp() ?? 'unknown');
        }

        $this->logSecuriteRepository->save($log);
    }
}
