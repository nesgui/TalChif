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
}
