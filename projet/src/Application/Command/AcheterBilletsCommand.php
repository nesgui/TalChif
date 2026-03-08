<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Command pour acheter des billets.
 * Immutable, contient uniquement les données nécessaires.
 */
final readonly class AcheterBilletsCommand
{
    /**
     * @param array<int, int> $panier [id_evenement => quantite]
     */
    public function __construct(
        public int $userId,
        public array $panier,
        public string $methodePaiement,
        public string $telephone
    ) {
    }
}
