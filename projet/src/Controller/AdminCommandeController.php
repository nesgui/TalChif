<?php

namespace App\Controller;

use App\Application\Command\RejeterPaiementCommand;
use App\Application\Command\ValiderPaiementCommand;
use App\Application\Handler\RejeterPaiementHandler;
use App\Application\Handler\ValiderPaiementHandler;
use App\Entity\Evenement;
use App\Entity\LogSecurite;
use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\LogSecuriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard admin : validation manuelle des paiements Mobile Money.
 */
#[Route('/admin/commandes')]
#[IsGranted('ROLE_ADMIN')]
final class AdminCommandeController extends AbstractController
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private LogSecuriteRepository $logSecuriteRepository,
        private EvenementRepository $evenementRepository,
        private BilletRepository $billetRepository,
        private EntityManagerInterface $entityManager,
        private ValiderPaiementHandler $validerPaiementHandler,
        private RejeterPaiementHandler $rejeterPaiementHandler
    ) {
    }

    #[Route('', name: 'admin.commande.index', methods: ['GET'])]
    public function index(): Response
    {
        $pending = $this->commandeRepository->findPending();
        $paid = $this->commandeRepository->findPaid();
        $expired = $this->commandeRepository->findExpired();
        $rejected = $this->commandeRepository->findRejected();

        $totalEncaisse = $this->commandeRepository->getTotalEncaisse();
        $totalCommission = $this->commandeRepository->getTotalCommission();
        $logs = $this->logSecuriteRepository->findRecent(50);
        $evenementsAPayer = $this->evenementRepository->findPastEventsNonPayes();
        $evenementsAvecSolde = [];
        foreach ($evenementsAPayer as $evt) {
            $evenementsAvecSolde[] = [
                'evenement' => $evt,
                'soldeNet' => $this->billetRepository->calculateNetRevenue($evt),
            ];
        }

        return $this->render('admin_commande/index.html.twig', [
            'pending' => $pending,
            'paid' => $paid,
            'expired' => $expired,
            'rejected' => $rejected,
            'totalEncaisse' => $totalEncaisse,
            'totalCommission' => $totalCommission,
            'logs' => $logs,
            'evenementsAPayer' => $evenementsAvecSolde,
        ]);
    }

    #[Route('/valider/{reference}', name: 'admin.commande.valider', methods: ['POST'], requirements: ['reference' => '[A-Z0-9\-]+'])]
    public function valider(Request $request, string $reference): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('valider_' . $reference, $token)) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin.commande.index');
        }
        $montant = (float) ($request->request->get('montant_recu', 0) ?? 0);
        $numero = trim((string) $request->request->get('numero_expediteur', ''));
        if ($montant <= 0 || $numero === '') {
            $this->addFlash('error', 'Montant et numéro expéditeur obligatoires.');
            return $this->redirectToRoute('admin.commande.index');
        }
        try {
            $command = new ValiderPaiementCommand(
                referenceCommande: $reference,
                montantRecu: $montant,
                numeroClient: $numero,
                validateurId: $this->getUser()->getId()
            );
            
            $this->validerPaiementHandler->handle($command);
            $this->addFlash('success', "Paiement validé. Les billets ont été générés pour la commande {$reference}.");
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('admin.commande.index');
    }

    #[Route('/rejeter/{reference}', name: 'admin.commande.rejeter', methods: ['POST'], requirements: ['reference' => '[A-Z0-9\-]+'])]
    public function rejeter(Request $request, string $reference): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rejeter_' . $reference, $token)) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin.commande.index');
        }
        $raison = trim((string) $request->request->get('raison', ''));
        try {
            $command = new RejeterPaiementCommand(
                referenceCommande: $reference,
                raison: $raison,
                validateurId: $this->getUser()->getId()
            );
            
            $this->rejeterPaiementHandler->handle($command);
            $this->addFlash('success', "Commande {$reference} rejetée.");
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('admin.commande.index');
    }

    #[Route('/evenement/{id}/settle', name: 'admin.commande.settle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function settleEvenement(Evenement $evenement, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('settle_' . $evenement->getId(), $token)) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin.commande.index');
        }
        if ($evenement->isOrganisateurPaye()) {
            $this->addFlash('warning', 'Cet événement a déjà été réglé.');
            return $this->redirectToRoute('admin.commande.index');
        }
        $now = new \DateTimeImmutable();
        if ($evenement->getDateEvenement() > $now) {
            $this->addFlash('error', 'L\'événement n\'est pas encore terminé.');
            return $this->redirectToRoute('admin.commande.index');
        }
        $montantNet = $this->billetRepository->calculateNetRevenue($evenement);
        $organisateur = $evenement->getOrganisateur();
        $tel = $organisateur?->getTelephone() ?? 'N/A';
        $evenement->setOrganisateurPaye(true);
        $this->entityManager->flush();

        $log = new LogSecurite();
        $log->setAction('SETTLE_ORGANISATEUR');
        $log->setReferenceCommande($evenement->getSlug());
        $log->setDetails("Événement #{$evenement->getId()} {$evenement->getNom()} - Solde net: {$montantNet} FCFA - Tél: {$tel} - Validé par {$this->getUser()->getEmail()}");
        $log->setUtilisateur($this->getUser());
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->addFlash('success', "Événement « {$evenement->getNom()} » marqué comme réglé. Effectuez le transfert Mobile Money vers {$tel}.");
        return $this->redirectToRoute('admin.commande.index');
    }

    #[Route('/export', name: 'admin.commande.export', methods: ['GET'])]
    public function export(): Response
    {
        $commandes = array_merge(
            $this->commandeRepository->findPaid(),
            $this->commandeRepository->findPending(),
            $this->commandeRepository->findExpired(),
            $this->commandeRepository->findRejected()
        );

        $response = new StreamedResponse(function () use ($commandes) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Référence', 'Client', 'Téléphone', 'Montant', 'Commission', 'Statut', 'Date', 'Validé par']);
            foreach ($commandes as $c) {
                fputcsv($handle, [
                    $c->getReference(),
                    $c->getClient()->getEmail(),
                    $c->getNumeroClient(),
                    $c->getMontantTotal(),
                    $c->getCommissionPlateforme(),
                    $c->getStatut(),
                    $c->getCreatedAt()?->format('Y-m-d H:i'),
                    $c->getValidePar()?->getEmail() ?? '',
                ]);
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="commandes_' . date('Y-m-d') . '.csv"');
        return $response;
    }
}
