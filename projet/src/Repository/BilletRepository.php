<?php

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Billet>
 */
class BilletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Billet::class);
    }

    public function findByClient(User $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }

    public function findByEvenement(Evenement $evenement): array
    {
        return $this->findBy(['evenement' => $evenement], ['createdAt' => 'ASC']);
    }

    public function findValidTickets(): array
    {
        return $this->findBy(['isValide' => true, 'isUtilise' => false]);
    }

    public function findUsedTickets(): array
    {
        return $this->findBy(['isUtilise' => true], ['dateUtilisation' => 'DESC']);
    }

    public function findByQrCode(string $qrCode): ?Billet
    {
        return $this->findOneBy(['qrCode' => $qrCode]);
    }

    public function findPaidTickets(): array
    {
        return $this->findBy(['statutPaiement' => 'PAYE'], ['createdAt' => 'DESC']);
    }

    public function findPendingTickets(): array
    {
        return $this->findBy(['statutPaiement' => 'EN_ATTENTE'], ['createdAt' => 'ASC']);
    }

    public function countByEvenement(Evenement $evenement): int
    {
        return $this->count(['evenement' => $evenement]);
    }

    public function countSoldByEvenement(Evenement $evenement): int
    {
        return $this->count(['evenement' => $evenement, 'statutPaiement' => 'PAYE']);
    }

    public function countByType(Evenement $evenement, string $type): int
    {
        return $this->count(['evenement' => $evenement, 'type' => $type, 'statutPaiement' => 'PAYE']);
    }

    public function findTicketsByOrganisateur(User $organisateur): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('e.organisateur = :organisateur')
            ->setParameter('organisateur', $organisateur)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTicketsForExport(Evenement $evenement): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.client', 'c')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBilletsAVenir(User $client): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('b.client = :client')
            ->andWhere('b.statutPaiement = :paid')
            ->andWhere('e.dateEvenement > :now')
            ->setParameter('client', $client)
            ->setParameter('paid', 'PAYE')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.dateEvenement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBilletsPasses(User $client): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('b.client = :client')
            ->andWhere('b.statutPaiement = :paid')
            ->andWhere('e.dateEvenement <= :now')
            ->setParameter('client', $client)
            ->setParameter('paid', 'PAYE')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.dateEvenement', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
