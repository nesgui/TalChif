<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Entity\Commande;

/**
 * Port (interface) pour le repository Commande.
 */
interface CommandeRepositoryInterface
{
    public function findByReference(string $reference): ?Commande;

    public function findByDepositId(string $depositId): ?Commande;

    public function referenceExists(string $reference): bool;

    public function findPendingExpired(\DateTimeImmutable $expirationDate): array;

    public function save(Commande $commande): void;

    public function flush(): void;
}
