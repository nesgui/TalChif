<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ObtenirMesCommandesQuery;
use App\Repository\CommandeRepository;
use App\Repository\UserRepository;

/**
 * Handler pour obtenir les commandes d'un utilisateur.
 */
final class ObtenirMesCommandesHandler
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * @return array{pending: array, paid: array, expired: array, rejected: array}
     */
    public function handle(ObtenirMesCommandesQuery $query): array
    {
        $user = $this->userRepository->find($query->userId);
        if (!$user) {
            return ['pending' => [], 'paid' => [], 'expired' => [], 'rejected' => []];
        }

        $commandes = $this->commandeRepository->findByClient($user);

        return [
            'pending'  => array_values(array_filter($commandes, fn($c) => $c->isPending() && !$c->estExpiree())),
            'paid'     => array_values(array_filter($commandes, fn($c) => $c->isPaid())),
            'expired'  => array_values(array_filter($commandes, fn($c) => $c->isExpired())),
            'rejected' => array_values(array_filter($commandes, fn($c) => $c->isRejected())),
        ];
    }
}
