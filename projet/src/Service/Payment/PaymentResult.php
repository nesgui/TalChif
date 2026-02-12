<?php

namespace App\Service\Payment;

/**
 * Résultat d'une tentative de paiement.
 */
final class PaymentResult
{
    public function __construct(
        private bool $success,
        private string $transactionId,
        private string $message = '',
        private ?\Throwable $exception = null
    ) {
    }

    public static function succes(string $transactionId, string $message = 'Paiement effectué.'): self
    {
        return new self(true, $transactionId, $message);
    }

    public static function echec(string $transactionId, string $message, ?\Throwable $e = null): self
    {
        return new self(false, $transactionId, $message, $e);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
