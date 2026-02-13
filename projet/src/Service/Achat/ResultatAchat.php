<?php

declare(strict_types=1);

namespace App\Service\Achat;

/**
 * Résultat d'un achat réussi (après paiement et création des billets).
 */
final class ResultatAchat
{
    public function __construct(
        private string $idTransaction,
        private string $messagePaiement
    ) {
    }

    public function getIdTransaction(): string
    {
        return $this->idTransaction;
    }

    public function getMessagePaiement(): string
    {
        return $this->messagePaiement;
    }
}
