<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ObtenirMesCommandesQuery;
use App\Domain\Repository\CommandeRepositoryInterface;
use App\Repository\CommandeRepository;

/**
 * Handler pour obtenir les commandes d'un utilisateur.
 */
final class ObtenirMesCommandesHandler
{
    public function __construct(
        private CommandeRepository $commandeRepository
    ) {
    }

    /**
     * @return array{pending: array, paid: array, expired: array, rejected: array}
     */
    public function handle(ObtenirMesCommandesQuery $query): array
    {
        $commandes = $this->commandeRepository->findByClient(
            $this->commandeRepository->find($query->userId)
        );

        return [
            'pending' => array_filter($commandes, fn($c) => $c->isPending() && !$c->estExpiree()),
            'paid' => array_filter($commandes, fn($c) => $c->isPaid()),
            'expired' => array_filter($commandes, fn($c) => $c->isExpired()),
            'rejected' => array_filter($commandes, fn($c) => $c->isRejected()),
        ];
    }
}
