<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP pour l'API PawaPay.
 */
final class PawaPayClient
{
    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $apiToken,
        private string $currency,
        private array $correspondents
    ) {
    }

    /**
     * Initie un dépôt via l'API PawaPay.
     */
    public function initierDepot(
        string $depositId,
        float $montant,
        string $telephone,
        string $methode,
        string $description
    ): void {
        $correspondent = $this->correspondents[$methode] ?? null;
        if (!$correspondent) {
            throw new \InvalidArgumentException("Méthode de paiement non supportée: {$methode}");
        }

        $phoneNumber = $this->normaliserNumero($telephone);

        $payload = [
            'depositId' => $depositId,
            'amount' => (string) $montant,
            'currency' => $this->currency,
            'payer' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'provider' => $correspondent,
                    'phoneNumber' => $phoneNumber,
                ],
            ],
            'customerMessage' => $this->sanitizeCustomerMessage($description),
        ];

        $this->logger->info('PawaPay demande depot', [
            'depositId' => $depositId,
            'provider' => $correspondent,
            'currency' => $this->currency,
            'amount' => (string) $montant,
            'phone' => $phoneNumber,
        ]);

        $response = $this->client->request('POST', $this->apiUrl('/deposits'), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode !== 200) {
            $this->logger->error('PawaPay API error', [
                'statusCode' => $statusCode,
                'content' => $content,
            ]);
            throw new \RuntimeException("PawaPay API error: {$statusCode}");
        }

        $this->logger->info('PawaPay dépôt initié', [
            'depositId' => $depositId,
            'statusCode' => $statusCode,
        ]);
    }

    /**
     * Vérifie le statut d'un dépôt.
     */
    public function verifierStatut(string $depositId): array
    {
        $response = $this->client->request('GET', $this->apiUrl("/deposits/{$depositId}"), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode !== 200) {
            throw new \RuntimeException("PawaPay API error: {$statusCode}");
        }

        return json_decode($content, true);
    }

    /**
     * Vérifie le statut d'un dépôt et retourne le statut simplifié.
     */
    public function verifierStatutDepot(string $depositId): string
    {
        try {
            $data = $this->verifierStatut($depositId);
            return $data['status'] ?? 'UNKNOWN';
        } catch (\Throwable $e) {
            $this->logger->error('Erreur vérification statut dépôt', [
                'depositId' => $depositId,
                'error' => $e->getMessage()
            ]);
            return 'ERROR';
        }
    }

    private function normaliserNumero(string $telephone): string
    {
        $digits = preg_replace('/\D+/', '', $telephone) ?? '';
        if ($digits === '') {
            return $telephone;
        }

        // Format attendu pour PawaPay: MSISDN sans "+"
        // Si le numero contient deja un indicatif supporte, on le conserve.
        foreach (['235', '237', '225'] as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                return $digits;
            }
        }

        // Fallback historique projet: prefixe Tchad.
        $digits = '235' . ltrim($digits, '0');
        return $digits;
    }

    private function apiUrl(string $path): string
    {
        $base = rtrim($this->baseUrl, '/');

        // Force la version v2 si elle n'est pas deja incluse.
        if (!preg_match('#/v\d+$#', $base)) {
            $base .= '/v2';
        }

        return $base . $path;
    }

    private function sanitizeCustomerMessage(string $message): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $message) ?? '';
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? '';

        if ($normalized === '') {
            return 'TalChif payment';
        }

        // Evite les messages trop longs.
        return substr($normalized, 0, 60);
    }
}
