<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Command pour créer une commande Mobile Money.
 */
final readonly class CreerCommandeCommand
{
    /**
     * @param array<int, int> $panier [id_evenement => quantite]
     */
    public function __construct(
        public string $checkoutEmail,
        public array $panier,
        public string $methodePaiement,
        public string $numeroClient
    ) {
    }
}
