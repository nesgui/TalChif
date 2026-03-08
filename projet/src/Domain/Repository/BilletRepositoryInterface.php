<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Entity\Billet;

/**
 * Port (interface) pour le repository Billet.
 */
interface BilletRepositoryInterface
{
    public function findByQrCode(string $qrCode): ?Billet;
    
    public function findByUser(int $userId): array;
    
    public function save(Billet $billet): void;
    
    public function flush(): void;
}
