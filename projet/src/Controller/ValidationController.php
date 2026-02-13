<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Repository\BilletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur de validation des billets (scan QR par les organisateurs).
 */
final class ValidationController extends AbstractController
{
    public function __construct(
        private BilletRepository $billetRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.validation.debut_offset')]
        private string $validationDebutOffset,
        #[Autowire(param: 'app.validation.fin_offset')]
        private string $validationFinOffset
    ) {
    }

    #[Route('/validation', name: 'validation.index')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function index(): Response
    {
        return $this->render('validation/index.html.twig');
    }

    #[Route('/api/validation/scan', name: 'validation.scan', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function scanBillet(Request $request): JsonResponse
    {
        $qrCode = $request->request->get('qrCode');
        
        if (!$qrCode) {
            return new JsonResponse([
                'success' => false,
                'message' => 'QR Code requis'
            ], 400);
        }

        $billet = $this->billetRepository->findOneBy(['qrCode' => $qrCode]);

        if (!$billet) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Billet non trouvé',
                'type' => 'NOT_FOUND'
            ], 404);
        }

        // Anti-fraude : seul l'organisateur de l'événement ou un admin peut valider
        $evenement = $billet->getEvenement();
        $user = $this->getUser();
        $isOrganisateur = $evenement->getOrganisateur() && $evenement->getOrganisateur()->getId() === $user->getId();
        $isAdmin = $user->isAdmin();
        if (!$isOrganisateur && !$isAdmin) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à valider les billets de cet événement',
                'type' => 'FORBIDDEN'
            ], 403);
        }

        // Billet invalide (annulé / remboursé)
        if (!$billet->isValide()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Billet invalide (annulé ou remboursé)',
                'type' => 'INVALID_TICKET',
                'billet' => [
                    'id' => $billet->getId(),
                    'qrCode' => $billet->getQrCode(),
                    'statutPaiement' => $billet->getStatutPaiement()
                ]
            ], 400);
        }

        // Vérifier si le billet est déjà utilisé
        if ($billet->isUtilise()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Billet déjà utilisé',
                'type' => 'ALREADY_USED',
                'billet' => [
                    'id' => $billet->getId(),
                    'qrCode' => $billet->getQrCode(),
                    'type' => $billet->getType(),
                    'evenement' => $billet->getEvenement()->getNom(),
                    'client' => $billet->getClient()->getEmail(),
                    'dateUtilisation' => $billet->getDateUtilisation()?->format('d/m/Y H:i:s')
                ]
            ], 400);
        }

        // Vérifier si le billet est payé
        if ($billet->getStatutPaiement() !== 'PAYE') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Billet non payé',
                'type' => 'NOT_PAID',
                'billet' => [
                    'id' => $billet->getId(),
                    'qrCode' => $billet->getQrCode(),
                    'type' => $billet->getType(),
                    'statutPaiement' => $billet->getStatutPaiement()
                ]
            ], 400);
        }

        // Vérifier si l'événement est dans la fenêtre de validation (configurable)
        $evenement = $billet->getEvenement();
        $now = new \DateTimeImmutable();
        $eventDate = $evenement->getDateEvenement();
        $validationStart = $eventDate->modify($this->validationDebutOffset);
        $validationEnd = $eventDate->modify($this->validationFinOffset);

        if ($now < $validationStart) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Événement pas encore commencé',
                'type' => 'TOO_EARLY',
                'billet' => [
                    'id' => $billet->getId(),
                    'qrCode' => $billet->getQrCode(),
                    'evenement' => $evenement->getNom(),
                    'dateEvenement' => $eventDate->format('d/m/Y H:i'),
                    'validationStart' => $validationStart->format('d/m/Y H:i')
                ]
            ], 400);
        }

        if ($now > $validationEnd) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Événement terminé',
                'type' => 'TOO_LATE',
                'billet' => [
                    'id' => $billet->getId(),
                    'qrCode' => $billet->getQrCode(),
                    'evenement' => $evenement->getNom(),
                    'dateEvenement' => $eventDate->format('d/m/Y H:i'),
                    'validationEnd' => $validationEnd->format('d/m/Y H:i')
                ]
            ], 400);
        }

        // Marquer le billet comme utilisé (date + utilisateur ayant validé)
        $billet->setUtilise(true);
        $billet->setDateUtilisation($now);
        $billet->setValidePar($this->getUser());

        $this->entityManager->flush();

        // Journaliser la validation
        $this->addFlash('success', sprintf(
            'Billet %s validé avec succès pour %s',
            $billet->getQrCode(),
            $evenement->getNom()
        ));

        return new JsonResponse([
            'success' => true,
            'message' => 'Billet validé avec succès',
            'type' => 'VALIDATED',
            'billet' => [
                'id' => $billet->getId(),
                'qrCode' => $billet->getQrCode(),
                'type' => $billet->getType(),
                'prix' => $billet->getPrix(),
                'evenement' => [
                    'nom' => $evenement->getNom(),
                    'date' => $evenement->getDateEvenement()->format('d/m/Y H:i'),
                    'lieu' => $evenement->getLieu()
                ],
                'client' => $billet->getClient()->getEmail(),
                'dateValidation' => $now->format('d/m/Y H:i:s'),
                'validePar' => $this->getUser()->getEmail()
            ]
        ]);
    }

    #[Route('/api/validation/lookup/{qrCode}', name: 'validation.lookup', methods: ['GET'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function lookupBillet(string $qrCode): JsonResponse
    {
        $billet = $this->billetRepository->findOneBy(['qrCode' => $qrCode]);

        if (!$billet) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Billet non trouvé'
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'billet' => [
                'id' => $billet->getId(),
                'qrCode' => $billet->getQrCode(),
                'type' => $billet->getType(),
                'prix' => $billet->getPrix(),
                'statutPaiement' => $billet->getStatutPaiement(),
                'utilise' => $billet->isUtilise(),
                'dateUtilisation' => $billet->getDateUtilisation()?->format('d/m/Y H:i:s'),
                'evenement' => [
                    'nom' => $billet->getEvenement()->getNom(),
                    'date' => $billet->getEvenement()->getDateEvenement()->format('d/m/Y H:i'),
                    'lieu' => $billet->getEvenement()->getLieu()
                ],
                'client' => $billet->getClient()->getEmail(),
                'transactionId' => $billet->getTransactionId()
            ]
        ]);
    }

    #[Route('/validation/historique', name: 'validation.historique')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function historique(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Récupérer les billets utilisés récemment
        $billetsUtilises = $this->billetRepository->findUsedTickets($limit, $offset);

        return $this->render('validation/historique.html.twig', [
            'billets' => $billetsUtilises,
            'page' => $page,
            'limit' => $limit
        ]);
    }
}
