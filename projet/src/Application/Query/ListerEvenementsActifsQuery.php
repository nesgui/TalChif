<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query pour lister les événements actifs.
 */
final readonly class ListerEvenementsActifsQuery
{
    public function __construct(
        public ?string $recherche = null,
        public ?string $ville = null,
        public ?string $categorie = null,
        public int $page = 1,
        public int $limit = 20
    ) {
    }
}
