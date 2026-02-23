<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\TicketDesign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketDesign>
 */
class TicketDesignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketDesign::class);
    }

    public function findOneForEvenementAndType(Evenement $evenement, string $typeBillet): ?TicketDesign
    {
        return $this->createQueryBuilder('td')
            ->andWhere('td.evenement = :evenement')
            ->andWhere('UPPER(td.typeBillet) = :typeBillet')
            ->setParameter('evenement', $evenement)
            ->setParameter('typeBillet', strtoupper($typeBillet))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
