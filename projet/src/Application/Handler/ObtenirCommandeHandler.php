<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ObtenirCommandeQuery;
use App\Domain\Repository\CommandeRepositoryInterface;
use App\Entity\Commande;

/**
 * Handler pour obtenir une commande par référence.
 */
final class ObtenirCommandeHandler
{
    public function __construct(
        private CommandeRepositoryInterface $commandeRepository
    ) {
    }

    public function handle(ObtenirCommandeQuery $query): ?Commande
    {
        $commande = $this->commandeRepository->findByReference($query->reference);

        // Vérifier l'appartenance si userId fourni
        if ($commande && $query->userId !== null) {
            if ($commande->getClient()->getId() !== $query->userId) {
                throw new \RuntimeException('Accès non autorisé à cette commande.');
            }
        }

        return $commande;
    }
}
