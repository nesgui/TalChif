<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Repository\EvenementRepositoryInterface;
use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter Doctrine pour EvenementRepositoryInterface.
 * Implémente le port défini dans le domaine.
 */
final class DoctrineEvenementRepository implements EvenementRepositoryInterface
{
    public function __construct(
        private EvenementRepository $doctrineRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findById(int $id): ?Evenement
    {
        return $this->doctrineRepository->find($id);
    }

    public function findByIdWithLock(int $id): ?Evenement
    {
        return $this->doctrineRepository->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    public function findActiveEvents(): array
    {
        return $this->doctrineRepository->findBy(['isActive' => true]);
    }

    public function save(Evenement $evenement): void
    {
        $this->entityManager->persist($evenement);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
