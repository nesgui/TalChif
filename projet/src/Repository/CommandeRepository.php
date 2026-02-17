<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findByReference(string $reference): ?Commande
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function referenceExiste(string $reference): bool
    {
        return $this->findByReference($reference) !== null;
    }

    /** @return Commande[] */
    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :pending')
            ->andWhere('c.dateExpiration > :now')
            ->setParameter('pending', Commande::STATUT_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :expired')
            ->setParameter('expired', Commande::STATUT_EXPIRED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findPaid(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :paid')
            ->setParameter('paid', Commande::STATUT_PAID)
            ->orderBy('c.dateValidation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findRejected(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :rejected')
            ->setParameter('rejected', Commande::STATUT_REJECTED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findToExpire(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :pending')
            ->andWhere('c.dateExpiration <= :now')
            ->setParameter('pending', Commande::STATUT_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findByClient(User $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statut = :pending')
            ->andWhere('c.dateExpiration > :now')
            ->setParameter('pending', Commande::STATUT_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalEncaisse(): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.montantTotal)')
            ->where('c.statut = :paid')
            ->setParameter('paid', Commande::STATUT_PAID)
            ->getQuery()
            ->getSingleScalarResult();
        return (float) ($result ?? 0);
    }

    public function getTotalCommission(): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionPlateforme)')
            ->where('c.statut = :paid')
            ->setParameter('paid', Commande::STATUT_PAID)
            ->getQuery()
            ->getSingleScalarResult();
        return (float) ($result ?? 0);
    }

    /** @return Commande[] */
    public function findPaidByOrganisateur(User $organisateur): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.lignes', 'l')
            ->join('l.evenement', 'e')
            ->where('c.statut = :paid')
            ->andWhere('e.organisateur = :organisateur')
            ->setParameter('paid', Commande::STATUT_PAID)
            ->setParameter('organisateur', $organisateur)
            ->orderBy('c.dateValidation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
