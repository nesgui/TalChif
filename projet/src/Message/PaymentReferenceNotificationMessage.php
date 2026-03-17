<?php

declare(strict_types=1);

namespace App\Message;

final class PaymentReferenceNotificationMessage
{
    /**
     * @param array<int, array{email: string, telephone: ?string, evenement: ?string}> $destinataires
     */
    public function __construct(
        public readonly string $commandeReference,
        public readonly float $montantTotal,
        public readonly string $clientNom,
        public readonly string $clientEmail,
        public readonly string $clientTelephone,
        public readonly string $operateur,
        public readonly string $referenceTransactionClient,
        public readonly array $destinataires
    ) {
    }
}

