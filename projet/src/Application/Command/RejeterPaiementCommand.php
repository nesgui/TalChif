<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Command pour rejeter un paiement Mobile Money.
 */
final readonly class RejeterPaiementCommand
{
    public function __construct(
        public string $referenceCommande,
        public string $raison,
        public int $validateurId
    ) {
    }
}
