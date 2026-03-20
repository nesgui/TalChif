<?php

declare(strict_types=1);

namespace App\Service\Ticket;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class QrCodeGeneratorService
{
    public function __construct(
        #[Autowire('%env(APP_SECRET)%')]
        private string $appSecret
    ) {
    }

    /**
     * Genere un QR Code unique, securise et non predictible.
     * Base sur random_bytes + HMAC-SHA256.
     */
    public function generer(): string
    {
        $random = bin2hex(random_bytes(16));
        $timestamp = (string) time();
        $signature = substr(hash_hmac('sha256', $random . $timestamp, $this->appSecret), 0, 20);

        return 'BILLET-' . strtoupper($random) . '-' . strtoupper($signature);
    }
}
