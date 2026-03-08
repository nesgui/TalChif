<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query pour obtenir les billets d'un utilisateur.
 */
final readonly class ObtenirMesBilletsQuery
{
    public function __construct(
        public int $userId,
        public ?string $filtre = null // 'avenir', 'passes', null = tous
    ) {
    }
}
