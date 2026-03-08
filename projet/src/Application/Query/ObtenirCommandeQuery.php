<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query pour obtenir une commande par référence.
 */
final readonly class ObtenirCommandeQuery
{
    public function __construct(
        public string $reference,
        public ?int $userId = null // Pour vérifier l'appartenance
    ) {
    }
}
