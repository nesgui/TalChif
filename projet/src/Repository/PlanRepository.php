<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function findParCode(string $code): ?Plan
    {
        return $this->findOneBy(['code' => mb_strtoupper($code)]);
    }

    /**
     * @return Plan[]
     */
    public function findActifs(): array
    {
        return $this->findBy(['actif' => true], ['libelle' => 'ASC']);
    }
}
