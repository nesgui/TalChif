<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Command pour valider un paiement Mobile Money.
 */
final readonly class ValiderPaiementCommand
{
    public function __construct(
        public string $referenceCommande,
        public float $montantRecu,
        public string $numeroClient,
        public int $validateurId
    ) {
    }
}
