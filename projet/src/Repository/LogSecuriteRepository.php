<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LogSecurite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogSecurite>
 */
class LogSecuriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogSecurite::class);
    }

    /** @return LogSecurite[] */
    public function findRecent(int $limit = 100): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    public function findRecentPaginated(int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.utilisateur', 'u')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(LogSecurite $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
