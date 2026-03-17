<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidTelephoneException;

/**
 * Value Object représentant un numéro mobile (MSISDN) supporté par le checkout.
 * Immutable et auto-validant.
 */
final readonly class Telephone
{
    private function __construct(private string $value)
    {
        if (!$this->estValide($value)) {
            throw new InvalidTelephoneException(
                "Le numéro de téléphone '{$value}' n'est pas valide. Formats attendus: +235XXXXXXXX, +237XXXXXXXXX, +225XXXXXXXXXX"
            );
        }
    }

    public static function fromString(string $value): self
    {
        $cleaned = preg_replace('/\s/', '', trim($value));
        return new self($cleaned);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toFormattedString(): string
    {
        // Format: 235 XX XX XX XX
        if (preg_match('/^235(\d{2})(\d{2})(\d{2})(\d{2})$/', $this->value, $matches)) {
            return "235 {$matches[1]} {$matches[2]} {$matches[3]} {$matches[4]}";
        }
        return $this->value;
    }

    public function equals(Telephone $other): bool
    {
        return $this->value === $other->value;
    }

    private function estValide(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return false;
        }

        // Pays supportés par le flow UI:
        // TD: 235 + 8 chiffres
        // CM: 237 + 9 chiffres
        // CI: 225 + 10 chiffres
        if (preg_match('/^235\d{8}$/', $digits)) {
            return true;
        }
        if (preg_match('/^237\d{9}$/', $digits)) {
            return true;
        }
        if (preg_match('/^225\d{10}$/', $digits)) {
            return true;
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
