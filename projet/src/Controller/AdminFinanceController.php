<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminFinanceController extends AbstractController
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private BilletRepository $billetRepository,
        private EvenementRepository $evenementRepository,
    ) {}

    #[Route('/admin/finance', name: 'admin.finance.index')]
    public function index(): Response
    {
        $totalEncaisse       = $this->commandeRepository->getTotalEncaisse();
        $totalCommission     = $this->commandeRepository->getTotalCommission();
        $totalNetOrganisateurs = $totalEncaisse - $totalCommission;
        $evenementsAPayer    = $this->evenementRepository->findPastEventsNonPayes();

        $evenementsAvecSolde = [];
        foreach ($evenementsAPayer as $evt) {
            $evenementsAvecSolde[] = [
                'evenement' => $evt,
                'soldeNet'  => $this->billetRepository->calculateNetRevenue($evt),
                'soldeBrut' => $this->billetRepository->calculateGrossRevenue($evt),
            ];
        }

        return $this->render('admin_finance/index.html.twig', [
            'totalEncaisse'          => $totalEncaisse,
            'totalCommission'        => $totalCommission,
            'totalNetOrganisateurs'  => $totalNetOrganisateurs,
            'evenementsAPayer'       => $evenementsAvecSolde,
            'nbCommandesPaid'        => count($this->commandeRepository->findPaid()),
            'nbCommandesPending'     => $this->commandeRepository->countPending(),
        ]);
    }
}
