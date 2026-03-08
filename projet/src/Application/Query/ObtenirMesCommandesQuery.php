<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query pour obtenir les commandes d'un utilisateur.
 */
final readonly class ObtenirMesCommandesQuery
{
    public function __construct(
        public int $userId
    ) {
    }
}
