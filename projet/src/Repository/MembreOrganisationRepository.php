<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MembreOrganisation>
 */
class MembreOrganisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MembreOrganisation::class);
    }

    /**
     * Appartenances d'un utilisateur, organisations actives uniquement.
     *
     * @return MembreOrganisation[]
     */
    public function findParUtilisateur(User $utilisateur): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.organisation', 'o')
            ->addSelect('o')
            ->where('m.utilisateur = :utilisateur')
            ->andWhere('o.actif = true')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('o.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAppartenance(User $utilisateur, Organisation $organisation): ?MembreOrganisation
    {
        return $this->findOneBy(['utilisateur' => $utilisateur, 'organisation' => $organisation]);
    }

    public function compterParOrganisation(Organisation $organisation): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.organisation = :organisation')
            ->setParameter('organisation', $organisation)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
