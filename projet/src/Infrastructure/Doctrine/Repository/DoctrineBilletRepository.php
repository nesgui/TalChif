<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Repository\BilletRepositoryInterface;
use App\Entity\Billet;
use App\Repository\BilletRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adapter Doctrine pour BilletRepositoryInterface.
 */
final class DoctrineBilletRepository implements BilletRepositoryInterface
{
    public function __construct(
        private BilletRepository $doctrineRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function findByQrCode(string $qrCode): ?Billet
    {
        return $this->doctrineRepository->findOneBy(['qrCode' => $qrCode]);
    }

    public function findByUser(int $userId): array
    {
        return $this->doctrineRepository->findBy(['client' => $userId]);
    }

    public function save(Billet $billet): void
    {
        $this->entityManager->persist($billet);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
