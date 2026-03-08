<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidTelephoneException;

/**
 * Value Object représentant un numéro de téléphone tchadien.
 * Immutable et auto-validant.
 */
final readonly class Telephone
{
    private function __construct(private string $value)
    {
        if (!$this->estValide($value)) {
            throw new InvalidTelephoneException(
                "Le numéro de téléphone '{$value}' n'est pas valide. Format attendu: 235 XX XX XX XX"
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
        $patterns = [
            '/^235\d{8}$/',                          // 235XXXXXXXX
            '/^\+235\d{8}$/',                        // +235XXXXXXXX
            '/^235\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/', // 235 XX XX XX XX
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
