<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private EvenementRepository $evenementRepository,
        private UserRepository $userRepository,
        private BilletRepository $billetRepository,
    ) {}

    #[Route('/admin', name: 'admin.dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        return $this->render('admin_dashboard/index.html.twig', [
            'stats' => [
                'commandes_en_attente'  => $this->commandeRepository->countPending(),
                'total_encaisse'        => $this->commandeRepository->getTotalEncaisse(),
                'total_commission'      => $this->commandeRepository->getTotalCommission(),
                'evenements_actifs'     => $this->evenementRepository->countActiveEvents(),
                'total_clients'         => $this->userRepository->countByRole('CLIENT'),
                'total_organisateurs'   => $this->userRepository->countByRole('ORGANISATEUR'),
                'commandes_payees'      => count($this->commandeRepository->findPaid()),
            ],
        ]);
    }
}
