<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByRole(string $role): array
    {
        return $this->findBy(['role' => $role]);
    }

    public function findClients(): array
    {
        return $this->findByRole('CLIENT');
    }

    public function findOrganisateurs(): array
    {
        return $this->findByRole('ORGANISATEUR');
    }

    public function findAdmins(): array
    {
        return $this->findByRole('ADMIN');
    }

    public function countByRole(string $role): int
    {
        return $this->count(['role' => $role]);
    }

    public function findVerifiedUsers(): array
    {
        return $this->findBy(['isVerified' => true]);
    }

    public function findUnverifiedUsers(): array
    {
        return $this->findBy(['isVerified' => false]);
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->remove($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Pagination pour les listes (évite de charger des milliers d'enregistrements en une fois).
     */
    public function findPaginated(int $page = 1, int $limit = 100, ?string $search = null): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->applySearch($qb, $search);

        return $qb->getQuery()->getResult();
    }

    public function countTotal(?string $search = null): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)');
        $this->applySearch($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applySearch($qb, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }
        $term = '%' . trim($search) . '%';
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('u.nom', ':search'),
                $qb->expr()->like('u.prenom', ':search')
            )
        )->setParameter('search', $term);
    }
}
