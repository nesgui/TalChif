<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ExpirerCommandesCommand;
use App\Domain\Repository\CommandeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handler pour expirer les commandes en attente.
 */
final class ExpirerCommandesHandler
{
    public function __construct(
        private CommandeRepositoryInterface $commandeRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function handle(ExpirerCommandesCommand $command): int
    {
        $now = new \DateTimeImmutable();
        $commandesExpirees = $this->commandeRepository->findPendingExpired($now);

        if (empty($commandesExpirees)) {
            return 0;
        }

        $this->entityManager->beginTransaction();
        try {
            foreach ($commandesExpirees as $commande) {
                $commande->marquerExpiree();
                $this->commandeRepository->save($commande);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return count($commandesExpirees);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
