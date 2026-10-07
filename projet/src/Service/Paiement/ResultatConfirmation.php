<?php

declare(strict_types=1);

namespace App\Service\Paiement;

/**
 * Issue d'une tentative de confirmation.
 *
 * « dejaConfirmee » distingue une reexecution inoffensive (idempotence) d'une
 * confirmation qui a reellement emis des billets.
 */
final readonly class ResultatConfirmation
{
    private function __construct(
        public bool $dejaConfirmee,
        public int $nombreBillets,
        public int $cashbackCredite,
    ) {
    }

    public static function confirmee(int $nombreBillets, int $cashbackCredite): self
    {
        return new self(false, $nombreBillets, $cashbackCredite);
    }

    public static function dejaConfirmee(): self
    {
        return new self(true, 0, 0);
    }
}
