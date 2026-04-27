<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Handler\ConfirmerDepotPawaPayHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Psr\Log\LoggerInterface;

/**
 * Controller pour recevoir les webhooks PawaPay.
 * Endpoint public pour les callbacks de paiement.
 */
#[AsController]
final class PawaPayWebhookController
{
    public function __construct(
        private ConfirmerDepotPawaPayHandler $handler,
        private LoggerInterface $logger,
        #[Autowire('%app.pawapay.webhook_secret%')]
        private string $webhookSecret
    ) {
    }

    /**
     * Endpoint webhook PawaPay.
     * Reçoit les callbacks de statut de dépôt.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->logger->info('PawaPay webhook appelé', [
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        // Vérifier la méthode
        if ($request->getMethod() !== 'POST') {
            return new JsonResponse(['error' => 'Method not allowed'], 405);
        }

        // Vérifier la signature HMAC avant tout traitement
        if (!empty($this->webhookSecret)) {
            $signature = $request->headers->get('x-pawapay-signature', '');
            if (empty($signature)) {
                $this->logger->warning('PawaPay webhook: en-tête de signature manquant', [
                    'ip' => $request->getClientIp(),
                ]);
                return new JsonResponse(['error' => 'Missing signature'], 401);
            }
            $expected = hash_hmac('sha256', $request->getContent(), $this->webhookSecret);
            if (!hash_equals($expected, $signature)) {
                $this->logger->warning('PawaPay webhook: signature HMAC invalide', [
                    'ip' => $request->getClientIp(),
                ]);
                return new JsonResponse(['error' => 'Invalid signature'], 401);
            }
        }

        // Parser le JSON
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('PawaPay webhook: JSON invalide', [
                'error' => json_last_error_msg(),
                'content' => $request->getContent(),
            ]);
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Valider les champs requis
        $depositId = $data['depositId'] ?? null;
        $status = $data['status'] ?? null;

        if (!$depositId || !$status) {
            $this->logger->error('PawaPay webhook: champs manquants', [
                'keys' => array_keys($data),
            ]);
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        try {
            $this->handler->handle($depositId, $status);
            
            $this->logger->info('PawaPay webhook traité avec succès', [
                'depositId' => $depositId,
                'status' => $status,
            ]);

            return new JsonResponse(['received' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('PawaPay webhook: erreur traitement', [
                'depositId' => $depositId,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'received' => false,
                'error' => 'processing_error'
            ], 500);
        }
    }
}
