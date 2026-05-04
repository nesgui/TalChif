<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommissionRateType;
use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Service\CommissionRateProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(Request $request, CommissionRateProvider $commissionRateProvider): Response
    {
        $totalEncaisse       = $this->commandeRepository->getTotalEncaisse();
        $totalCommission     = $this->commandeRepository->getTotalCommission();
        $totalNetOrganisateurs = $totalEncaisse - $totalCommission;
        $evenementsAPayer    = $this->evenementRepository->findPastEventsNonPayes();

        $fromRaw = (string) $request->query->get('from', '');
        $toRaw = (string) $request->query->get('to', '');
        $statusRaw = (string) $request->query->get('status', 'all');

        $from = null;
        if ($fromRaw !== '') {
            try {
                $from = new \DateTimeImmutable($fromRaw . ' 00:00:00');
            } catch (\Throwable) {
                $from = null;
            }
        }

        $to = null;
        if ($toRaw !== '') {
            try {
                $to = new \DateTimeImmutable($toRaw . ' 23:59:59');
            } catch (\Throwable) {
                $to = null;
            }
        }

        $allowedStatuses = [
            'all',
            Commande::STATUT_PAID,
            Commande::STATUT_PENDING,
            Commande::STATUT_PROCESSING,
            Commande::STATUT_REJECTED,
            Commande::STATUT_EXPIRED,
            Commande::STATUT_CANCELLED,
        ];
        if (!in_array($statusRaw, $allowedStatuses, true)) {
            $statusRaw = 'all';
        }

        $transactions = $this->commandeRepository->findForFinanceFiltered($from, $to, $statusRaw, 200);

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
            'transactions'           => $transactions,
            'commissionRate'         => $commissionRateProvider->getRate(),
            'transactionFilters'     => [
                'from' => $fromRaw,
                'to' => $toRaw,
                'status' => $statusRaw,
            ],
            'nbCommandesPaid'        => count($this->commandeRepository->findPaid()),
            'nbCommandesPending'     => $this->commandeRepository->countPending(),
        ]);
    }

    #[Route('/admin/finance/transactions/{id}', name: 'admin.finance.transaction_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function showTransaction(Commande $commande): Response
    {
        return $this->render('admin_finance/transaction_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/admin/finance/commission', name: 'admin.finance.commission_edit', methods: ['GET', 'POST'])]
    public function editCommission(Request $request, CommissionRateProvider $commissionRateProvider): Response
    {
        $appliedRate = $commissionRateProvider->getRate();
        $data = [
            'rate' => $appliedRate,
        ];

        $form = $this->createForm(CommissionRateType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rate = (float) ($form->get('rate')->getData() ?? 0);
            $commissionRateProvider->setRate($rate);
            $this->addFlash('success', 'Commission standard mise à jour.');
            return $this->redirectToRoute('admin.finance.index');
        }

        return $this->render('admin_finance/commission_edit.html.twig', [
            'form' => $form->createView(),
            'appliedRate' => $appliedRate,
        ]);
    }
}
