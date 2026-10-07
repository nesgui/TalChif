<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Payment\PaymentVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/balance')]
#[IsGranted('ROLE_USER')]
final class BalanceController extends AbstractController
{
    public function __construct(
        private PaymentVerificationService $paymentVerificationService
    ) {
    }

    #[Route('', name: 'balance.index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('balance/index.html.twig', [
            'balance' => $user->getBalance(),
            'user' => $user,
        ]);
    }

    /**
     * Rapprochement manuel des paiements en attente. Reserve aux administrateurs.
     */
    #[Route('/check-payments', name: 'balance.check_payments', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function verifierPaiements(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('balance_check_payments', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token de securite invalide.'], 403);
        }

        $resultats = $this->paymentVerificationService->checkPendingPayments();

        return new JsonResponse([
            'success' => true,
            'results' => $resultats,
            'total' => count($resultats),
            'processed' => count(array_filter($resultats, static fn (array $r): bool => (bool) $r['success'])),
        ]);
    }

    #[Route('/api/balance', name: 'balance.api', methods: ['GET'])]
    public function apiBalance(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'balance' => $user->getBalance(),
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);
    }
}
