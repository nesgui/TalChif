<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function findActiveEvents(): array
    {
        return $this->findBy(['isActive' => true], ['dateEvenement' => 'ASC']);
    }

    public function findUpcomingEvents(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('e')
            ->where('e.dateEvenement > :now')
            ->andWhere('e.isActive = :active')
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPastEvents(): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('e')
            ->where('e.dateEvenement < :now')
            ->andWhere('e.isActive = :active')
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOrganisateur(User $organisateur): array
    {
        return $this->findBy(['organisateur' => $organisateur], ['createdAt' => 'DESC']);
    }

    public function searchEvents(string $query): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.nom LIKE :query OR e.description LIKE :query OR e.lieu LIKE :query OR e.ville LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPopularEvents(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.placesVendues > 0')
            ->setParameter('active', true)
            ->orderBy('e.placesVendues', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAvailableEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isActive = :active')
            ->andWhere('e.placesDisponibles > e.placesVendues')
            ->setParameter('active', true)
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveEvents(): int
    {
        return $this->count(['isActive' => true]);
    }

    public function countByOrganisateur(User $organisateur): int
    {
        return $this->count(['organisateur' => $organisateur]);
    }

    public function save(Evenement $evenement, bool $flush = false): void
    {
        $this->getEntityManager()->persist($evenement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Evenement $evenement, bool $flush = false): void
    {
        $this->getEntityManager()->remove($evenement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
