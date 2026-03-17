<?php

namespace App\Controller;

use App\Service\Payment\PaymentVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/balance')]
class BalanceController extends AbstractController
{
    public function __construct(
        private PaymentVerificationService $paymentVerificationService
    ) {}

    #[Route('/', name: 'balance.index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('balance/index.html.twig', [
            'balance' => $user->getBalance(),
            'user' => $user
        ]);
    }

    #[Route('/check-payments', name: 'balance.check_payments', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkPayments(): JsonResponse
    {
        // Seul les admins peuvent vérifier les paiements
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $results = $this->paymentVerificationService->checkPendingPayments();
        
        return new JsonResponse([
            'success' => true,
            'results' => $results,
            'total' => count($results),
            'processed' => count(array_filter($results, fn($r) => $r['success']))
        ]);
    }

    #[Route('/api/balance', name: 'balance.api', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function apiBalance(): JsonResponse
    {
        $user = $this->getUser();
        
        return new JsonResponse([
            'balance' => $user->getBalance(),
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }
}
