<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Entity\Evenement;

/**
 * Port (interface) pour le repository Evenement.
 * Le domaine définit le contrat, l'infrastructure l'implémente.
 */
interface EvenementRepositoryInterface
{
    public function findById(int $id): ?Evenement;
    
    public function findByIdWithLock(int $id): ?Evenement;
    
    public function findActiveEvents(): array;
    
    public function save(Evenement $evenement): void;
    
    public function flush(): void;
}
