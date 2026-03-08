<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Query\ListerEvenementsActifsQuery;
use App\Domain\Repository\EvenementRepositoryInterface;
use App\Repository\EvenementRepository;

/**
 * Handler pour lister les événements actifs avec filtres.
 */
final class ListerEvenementsActifsHandler
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    /**
     * @return array{evenements: array, total: int, page: int, totalPages: int}
     */
    public function handle(ListerEvenementsActifsQuery $query): array
    {
        $evenements = $this->evenementRepository->findPaginated(
            page: $query->page,
            limit: $query->limit,
            search: $query->recherche,
            ville: $query->ville,
            categorie: $query->categorie
        );

        $total = $this->evenementRepository->countTotal(
            search: $query->recherche,
            ville: $query->ville,
            categorie: $query->categorie
        );

        $totalPages = (int) ceil($total / $query->limit);

        return [
            'evenements' => $evenements,
            'total' => $total,
            'page' => $query->page,
            'totalPages' => $totalPages,
        ];
    }
}
