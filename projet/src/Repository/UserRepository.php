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
}
