<?php

declare(strict_types=1);

namespace App\Service\Panier;

/**
 * Panier resolu : lignes affichables et total.
 *
 * @phpstan-type LignePanier array{
 *     id: int,
 *     quantite: int,
 *     type: string,
 *     sous_total: float,
 *     produit: array<string, mixed>
 * }
 */
final readonly class ContenuPanier
{
    /**
     * @param list<array<string, mixed>> $lignes
     */
    public function __construct(
        public array $lignes,
        public float $total,
    ) {
    }

    public function estVide(): bool
    {
        return $this->lignes === [];
    }

    public function nombreArticles(): int
    {
        return array_sum(array_map(static fn (array $ligne): int => (int) $ligne['quantite'], $this->lignes));
    }
}
