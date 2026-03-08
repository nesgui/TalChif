<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ObtenirEvenementQuery;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Entity\Evenement;

/**
 * Handler pour obtenir un événement par ID.
 */
final class ObtenirEvenementHandler
{
    public function __construct(
        private EvenementRepositoryInterface $evenementRepository
    ) {
    }

    public function handle(ObtenirEvenementQuery $query): ?Evenement
    {
        return $this->evenementRepository->findById($query->evenementId);
    }
}
