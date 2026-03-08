<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\MontantNegatifException;

/**
 * Value Object représentant un montant en XAF (Franc CFA).
 * Immutable et auto-validant.
 */
final readonly class Montant
{
    private function __construct(private float $value)
    {
        if ($value < 0) {
            throw new MontantNegatifException(
                "Le montant ne peut pas être négatif: {$value}"
            );
        }
    }

    public static function fromFloat(float $value): self
    {
        return new self(round($value, 2));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public function toFloat(): float
    {
        return $this->value;
    }

    public function ajouter(Montant $autre): self
    {
        return new self($this->value + $autre->value);
    }

    public function soustraire(Montant $autre): self
    {
        return new self($this->value - $autre->value);
    }

    public function multiplier(int $facteur): self
    {
        return new self($this->value * $facteur);
    }

    public function appliquerPourcentage(float $pourcentage): self
    {
        return new self($this->value * ($pourcentage / 100));
    }

    public function estSuperieurA(Montant $autre): bool
    {
        return $this->value > $autre->value;
    }

    public function estEgalA(Montant $autre): bool
    {
        return abs($this->value - $autre->value) < 0.01;
    }

    public function estPositif(): bool
    {
        return $this->value > 0;
    }

    public function __toString(): string
    {
        return number_format($this->value, 2, '.', ' ') . ' XAF';
    }
}
