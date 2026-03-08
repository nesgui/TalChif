<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query pour obtenir un événement par ID.
 */
final readonly class ObtenirEvenementQuery
{
    public function __construct(
        public int $evenementId
    ) {
    }
}
