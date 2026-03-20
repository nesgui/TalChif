<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Repository\CommandeRepositoryInterface;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter Doctrine pour CommandeRepositoryInterface.
 */
final class DoctrineCommandeRepository implements CommandeRepositoryInterface
{
    public function __construct(
        private CommandeRepository $doctrineRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findByReference(string $reference): ?Commande
    {
        return $this->doctrineRepository->findOneBy(['reference' => $reference]);
    }

    public function referenceExists(string $reference): bool
    {
        return $this->doctrineRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findPendingExpired(\DateTimeImmutable $expirationDate): array
    {
        return $this->doctrineRepository->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->andWhere('c.dateExpiration < :date')
            ->setParameter('statut', 'Pending')
            ->setParameter('date', $expirationDate)
            ->getQuery()
            ->getResult();
    }

    public function save(Commande $commande): void
    {
        $this->entityManager->persist($commande);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findByDepositId(string $depositId): ?Commande
    {
        return $this->doctrineRepository->findOneBy(['depositId' => $depositId]);
    }
}
