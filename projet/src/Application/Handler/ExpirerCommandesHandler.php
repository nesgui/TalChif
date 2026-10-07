<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ExpirerCommandesCommand;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handler pour expirer les commandes en attente.
 */
final class ExpirerCommandesHandler
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function handle(ExpirerCommandesCommand $command): int
    {
        $now = new \DateTimeImmutable();
        $commandesExpirees = $this->commandeRepository->findToExpire($now);

        if (empty($commandesExpirees)) {
            return 0;
        }

        $this->entityManager->beginTransaction();
        try {
            foreach ($commandesExpirees as $commande) {
                $commande->marquerExpiree();
                $this->entityManager->persist($commande);
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
