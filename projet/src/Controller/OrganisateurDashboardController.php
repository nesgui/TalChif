<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Repository\BilletRepository;
use App\Repository\EvenementRepository;
use App\Repository\LogSecuriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OrganisateurDashboardController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private BilletRepository $billetRepository,
        private LogSecuriteRepository $logSecuriteRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.commission_taux%')]
        private float $commissionTaux
    ) {
    }

    #[Route('/organisateur/reglements', name: 'organisateur.reglements')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function reglements(): Response
    {
        $user = $this->getUser();
        /** @var \App\Entity\User $user */

        $evenementsPayes = $this->evenementRepository->findBy(
            ['organisateur' => $user, 'organisateurPaye' => true],
            ['dateEvenement' => 'DESC']
        );

        $reglements = [];
        foreach ($evenementsPayes as $evenement) {
            $montantNet = $this->billetRepository->calculateNetRevenue($evenement);
            $log = $this->logSecuriteRepository->findOneBy(
                ['action' => 'SETTLE_ORGANISATEUR', 'referenceCommande' => $evenement->getSlug()],
                ['createdAt' => 'DESC']
            );

            $reglements[] = [
                'evenement' => $evenement,
                'montantNet' => $montantNet,
                'datePaiement' => $log?->getCreatedAt(),
                'admin' => $log?->getUtilisateur(),
            ];
        }

        return $this->render('organisateur_dashboard/reglements.html.twig', [
            'reglements' => $reglements,
        ]);
    }

    #[Route('/organisateur', name: 'organisateur.dashboard')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        $evenements = $this->evenementRepository->findBy(['organisateur' => $user], ['dateEvenement' => 'DESC']);

        $totalBrut = $this->billetRepository->calculateTotalRevenueByOrganisateur($user);
        $totalCommission = (float) round($totalBrut * $this->commissionTaux, 2);
        $soldeNet = $totalBrut - $totalCommission;

        // Statistiques globales
        $stats = [
            'total_evenements' => count($evenements),
            'total_billets_vendus' => $this->billetRepository->countSoldTicketsByOrganisateur($user),
            'total_revenus' => $totalBrut,
            'total_commission' => $totalCommission,
            'solde_net' => $soldeNet,
            'billets_a_venir' => $this->billetRepository->countUpcomingTicketsByOrganisateur($user),
        ];

        return $this->render('organisateur_dashboard/index.html.twig', [
            'evenements' => $evenements,
            'stats' => $stats
        ]);
    }

    #[Route('/organisateur/evenement/{id}/stats', name: 'organisateur.evenement.stats')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function evenementStats(Evenement $evenement): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        $stats = [
            'total_billets' => $this->billetRepository->countByEvenement($evenement),
            'billets_vendus' => $this->billetRepository->countSoldByEvenement($evenement),
            'billets_restants' => $evenement->getPlacesTotal() - $this->billetRepository->countSoldByEvenement($evenement),
            'billets_simple' => $this->billetRepository->countByType($evenement, 'simple'),
            'billets_vip' => $this->billetRepository->countByType($evenement, 'vip'),
            'revenus_bruts' => $this->billetRepository->calculateGrossRevenue($evenement),
            'revenus_nets' => $this->billetRepository->calculateNetRevenue($evenement),
            'taux_remplissage' => $evenement->getPlacesTotal() > 0 ? 
                round(($this->billetRepository->countSoldByEvenement($evenement) / $evenement->getPlacesTotal()) * 100, 1) : 0,
        ];

        return $this->render('organisateur_dashboard/evenement_stats.html.twig', [
            'evenement' => $evenement,
            'stats' => $stats
        ]);
    }

    #[Route('/organisateur/evenement/{id}/participants', name: 'organisateur.evenement.participants')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function participants(Evenement $evenement, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        $participants = $this->billetRepository->findParticipantsByEvenement($evenement, null, null);
        $totalParticipants = count($participants);

        return $this->render('organisateur_dashboard/participants.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
            'totalParticipants' => $totalParticipants
        ]);
    }

    #[Route('/organisateur/evenement/{id}/qrcodes', name: 'organisateur.evenement.qrcodes')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function qrCodes(Evenement $evenement, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $billets = $this->billetRepository->findPaidTicketsByEvenement($evenement, $limit, $offset);
        $totalBillets = $this->billetRepository->countSoldByEvenement($evenement);
        $totalPages = ceil($totalBillets / $limit);

        return $this->render('organisateur_dashboard/qrcodes.html.twig', [
            'evenement' => $evenement,
            'billets' => $billets,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalBillets' => $totalBillets
        ]);
    }

    #[Route('/organisateur/evenement/{id}/export', name: 'organisateur.evenement.export')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function exportParticipants(Evenement $evenement): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        $participants = $this->billetRepository->findTicketsForExport($evenement);
        
        $csv = "Nom,Email,Type de billet,Prix,Date d'achat,Statut paiement,Statut utilisation\n";
        
        foreach ($participants as $billet) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $billet->getClient()->getFullName(),
                $billet->getClient()->getEmail(),
                $billet->getType(),
                $billet->getPrix(),
                $billet->getCreatedAt()->format('d/m/Y H:i'),
                $billet->getStatutPaiement(),
                $billet->isUtilise() ? 'Utilisé' : 'Non utilisé'
            );
        }

        $filename = sprintf('participants_%s_%s.csv', 
            $evenement->getSlug(),
            date('Y-m-d_H-i-s')
        );

        return new BinaryFileResponse(
            $csv,
            $filename,
            'text/csv'
        );
    }

    #[Route('/organisateur/evenement/{id}/performance', name: 'organisateur.evenement.performance')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function performance(Evenement $evenement): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        // Statistiques de performance
        $performance = [
            'ventes_par_jour' => $this->billetRepository->getSalesByDay($evenement),
            'ventes_par_mois' => $this->billetRepository->getSalesByMonth($evenement),
            'types_populaires' => $this->billetRepository->getPopularTypes($evenement),
            'pics_ventes' => $this->billetRepository->getSalesPeaks($evenement),
            'taux_conversion' => $this->billetRepository->getConversionRate($evenement),
            'revenu_moyen_par_billet' => $this->billetRepository->getAverageTicketPrice($evenement),
        ];

        return $this->render('organisateur_dashboard/performance.html.twig', [
            'evenement' => $evenement,
            'performance' => $performance
        ]);
    }

    #[Route('/api/organisateur/evenement/{id}/stats', name: 'organisateur.evenement.stats_api')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function statsApi(Evenement $evenement): JsonResponse
    {
        $this->denyAccessUnlessGranted('EDIT', $evenement);

        $stats = [
            'total_billets' => $this->billetRepository->countByEvenement($evenement),
            'billets_vendus' => $this->billetRepository->countSoldByEvenement($evenement),
            'billets_restants' => $evenement->getPlacesTotal() - $this->billetRepository->countSoldByEvenement($evenement),
            'revenus_bruts' => $this->billetRepository->calculateGrossRevenue($evenement),
            'revenus_nets' => $this->billetRepository->calculateNetRevenue($evenement),
        ];

        return new JsonResponse($stats);
    }
}
