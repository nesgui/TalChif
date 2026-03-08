<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ObtenirMesBilletsQuery;
use App\Domain\Repository\BilletRepositoryInterface;
use App\Entity\Billet;

/**
 * Handler pour obtenir les billets d'un utilisateur.
 * Séparation lecture (Query) / écriture (Command).
 */
final class ObtenirMesBilletsHandler
{
    public function __construct(
        private BilletRepositoryInterface $billetRepository
    ) {
    }

    /**
     * @return array<Billet>
     */
    public function handle(ObtenirMesBilletsQuery $query): array
    {
        $billets = $this->billetRepository->findByUser($query->userId);

        if ($query->filtre === 'avenir') {
            return array_filter($billets, function (Billet $b) {
                $evenement = $b->getEvenement();
                return $evenement && $evenement->estAVenir();
            });
        }

        if ($query->filtre === 'passes') {
            return array_filter($billets, function (Billet $b) {
                $evenement = $b->getEvenement();
                return $evenement && $evenement->estPasse();
            });
        }

        return $billets;
    }
}
