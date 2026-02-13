<?php

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes pour l'entité Billet.
 *
 * @extends ServiceEntityRepository<Billet>
 */
class BilletRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%app.commission_taux%')]
        float $commissionTaux = 0.10
    ) {
        parent::__construct($registry, Billet::class);
        $this->commissionTaux = $commissionTaux;
    }

    private float $commissionTaux;

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
            ->setParameter('now', new \DateTimeImmutable())
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
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.dateEvenement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUsedTickets(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.evenement', 'e')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.validePar', 'v')
            ->where('b.isUtilise = :used')
            ->setParameter('used', true)
            ->orderBy('b.dateUtilisation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    // Méthodes pour le dashboard organisateur
    public function countSoldTicketsByOrganisateur(User $organisateur): int
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('e.organisateur = :organisateur')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('organisateur', $organisateur)
            ->setParameter('paid', 'PAYE')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function calculateTotalRevenueByOrganisateur(User $organisateur): int
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('e.organisateur = :organisateur')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('organisateur', $organisateur)
            ->setParameter('paid', 'PAYE')
            ->select('SUM(b.prix)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function countUpcomingTicketsByOrganisateur(User $organisateur): int
    {
        return $this->createQueryBuilder('b')
            ->join('b.evenement', 'e')
            ->where('e.organisateur = :organisateur')
            ->andWhere('b.statutPaiement = :paid')
            ->andWhere('e.dateEvenement > :now')
            ->setParameter('organisateur', $organisateur)
            ->setParameter('paid', 'PAYE')
            ->setParameter('now', new \DateTimeImmutable())
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function calculateGrossRevenue(Evenement $evenement): int
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('SUM(b.prix)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /** Revenu net après commission admin (taux configuré dans app.commission_taux). */
    public function calculateNetRevenue(Evenement $evenement): int
    {
        $brut = $this->calculateGrossRevenue($evenement);
        return (int) ($brut * (1 - $this->commissionTaux));
    }

    public function findParticipantsByEvenement(Evenement $evenement, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.client', 'c')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.prenom', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findPaidTicketsByEvenement(Evenement $evenement, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.client', 'c')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function getSalesByDay(Evenement $evenement): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('DATE(b.createdAt) as date, COUNT(b.id) as sales')
            ->groupBy('DATE(b.createdAt)')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getSalesByMonth(Evenement $evenement): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('DATE_FORMAT(b.createdAt, "%Y-%m") as month, COUNT(b.id) as sales')
            ->groupBy('DATE_FORMAT(b.createdAt, "%Y-%m")')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getPopularTypes(Evenement $evenement): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('b.type, COUNT(b.id) as count')
            ->groupBy('b.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getSalesPeaks(Evenement $evenement): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('HOUR(b.createdAt) as hour, COUNT(b.id) as sales')
            ->groupBy('HOUR(b.createdAt)')
            ->orderBy('sales', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function getConversionRate(Evenement $evenement): float
    {
        $totalViews = 1000; // Simulé - à implémenter avec des logs de vues
        $totalSales = $this->countSoldByEvenement($evenement);
        
        return $totalViews > 0 ? round(($totalSales / $totalViews) * 100, 2) : 0;
    }

    public function getAverageTicketPrice(Evenement $evenement): float
    {
        return $this->createQueryBuilder('b')
            ->where('b.evenement = :evenement')
            ->andWhere('b.statutPaiement = :paid')
            ->setParameter('evenement', $evenement)
            ->setParameter('paid', 'PAYE')
            ->select('AVG(b.prix)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
}
