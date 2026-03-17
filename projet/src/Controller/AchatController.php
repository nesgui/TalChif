<?php

namespace App\Controller;

use App\Application\Command\CreerCommandeCommand;
use App\Application\Handler\CreerCommandeHandler;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\LogSecurite;
use App\Message\PaymentReferenceNotificationMessage;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur des achats : panier, création commande Mobile Money, instructions, confirmation.
 * Workflow : créer commande → instructions paiement → validation admin → billets.
 */
final class AchatController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private CommandeRepository $commandeRepository,
        private EntityManagerInterface $entityManager,
        private CreerCommandeHandler $creerCommandeHandler,
        private MessageBusInterface $messageBus,
        #[Autowire('%app.momo.numero%')]
        private string $momoNumero,
        #[Autowire('%app.momo.beneficiaire%')]
        private string $momoBeneficiaire
    ) {
    }

    #[Route('/achat', name: 'achat.index')]
    #[IsGranted('ROLE_CLIENT')]
    public function index(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        if (empty($panier)) {
            $this->addFlash('warning', 'Votre panier est vide');
            return $this->redirectToRoute('panier.index');
        }

        $lignes = [];
        $total = 0.0;

        foreach ($panier as $id => $quantite) {
            $evenement = $this->evenementRepository->find($id);
            if (!$evenement || !$evenement->isActive()) {
                continue;
            }
            $prixMin = $evenement->getPrixSimple();
            $sousTotal = $prixMin * $quantite;
            $total += $sousTotal;
            $lignes[] = [
                'id' => $id,
                'quantite' => $quantite,
                'produit' => [
                    'id' => $evenement->getId(),
                    'slug' => $evenement->getSlug(),
                    'titre' => $evenement->getNom(),
                    'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
                    'prix_min' => $prixMin,
                    'prix_vip' => $evenement->getPrixVip(),
                    'ville' => $evenement->getVille(),
                    'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                ],
                'sous_total' => $sousTotal,
            ];
        }

        return $this->render('achat/index.html.twig', [
            'lignes' => $lignes,
            'total' => $total,
        ]);
    }

    #[Route(
        '/evenements/{slug}-{id}/achat',
        name: 'achat.evenement',
        requirements: ['id' => '\\d+', 'slug' => '[a-z0-9-]+']
    )]
    #[IsGranted('ROLE_CLIENT')]
    public function achatEvenement(string $slug, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if (!$evenement) {
            throw $this->createNotFoundException('Événement non trouvé');
        }
        if ($evenement->getSlug() !== $slug) {
            return $this->redirectToRoute('achat.evenement', [
                'slug' => $evenement->getSlug(),
                'id' => $evenement->getId(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }
        if (!$evenement->isActive()) {
            $this->addFlash('error', 'Cet événement n\'est pas disponible');
            return $this->redirectToRoute('evenement.show', ['slug' => $evenement->getSlug(), 'id' => $evenement->getId()]);
        }
        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Cet événement est complet');
            return $this->redirectToRoute('evenement.show', ['slug' => $evenement->getSlug(), 'id' => $evenement->getId()]);
        }

        return $this->render('achat/evenement.html.twig', [
            'evenement' => [
                'id' => $evenement->getId(),
                'slug' => $evenement->getSlug(),
                'titre' => $evenement->getNom(),
                'description' => $evenement->getDescription(),
                'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
                'prix_simple' => $evenement->getPrixSimple(),
                'prix_vip' => $evenement->getPrixVip(),
                'ville' => $evenement->getVille(),
                'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                'lieu' => $evenement->getLieu(),
                'places_restantes' => $evenement->getPlacesRestantes(),
            ],
        ]);
    }

    #[Route('/achat/paiement', name: 'achat.paiement', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function paiement(Request $request, SessionInterface $session): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour effectuer un achat');
            return $this->redirectToRoute('auth.login');
        }

        $panier = $session->get('panier', []);
        if (empty($panier)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('panier.index');
        }

        $methodePaiement = $request->request->get('methode_paiement');
        $telephone = $request->request->get('telephone');
        if (empty($methodePaiement) || empty(trim((string) $telephone))) {
            $this->addFlash('error', 'Informations de paiement invalides');
            return $this->redirectToRoute('achat.index');
        }

        try {
            $command = new CreerCommandeCommand(
                userId: $user->getId(),
                panier: $panier,
                methodePaiement: (string) $methodePaiement,
                numeroClient: trim((string) $telephone)
            );

            $commande = $this->creerCommandeHandler->handle($command);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('achat.index');
        }

        $session->remove('panier');
        $this->addFlash('info', 'Effectuez le transfert Mobile Money dans les 10 minutes. Mettez la référence dans le message.');

        return $this->redirectToRoute('achat.instructions', [
            'reference' => $commande->getReference(),
        ]);
    }

    #[Route('/api/payments/create', name: 'api.payments.create', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function createPayment(Request $request, SessionInterface $session): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'ok' => false,
                'message' => 'Vous devez être connecté pour effectuer un paiement.'
            ], 401);
        }

        $panier = $session->get('panier', []);
        if (empty($panier)) {
            return $this->json([
                'ok' => false,
                'message' => 'Votre panier est vide.'
            ], 400);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = $request->request->all();
        }

        $methodePaiement = (string) ($data['methode_paiement'] ?? '');
        $telephone = trim((string) ($data['telephone'] ?? ''));
        $country = strtoupper((string) ($data['country'] ?? ''));

        if ($methodePaiement === '' || $telephone === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Méthode et numéro de téléphone sont obligatoires.'
            ], 422);
        }
        if (!$this->isTelephoneCompatibleWithMethod($methodePaiement, $telephone)) {
            return $this->json([
                'ok' => false,
                'message' => 'Le numéro ne correspond pas à l’opérateur choisi (Airtel/Moov).'
            ], 422);
        }

        try {
            $commande = $this->creerCommandeHandler->handle(new CreerCommandeCommand(
                userId: $user->getId(),
                panier: $panier,
                methodePaiement: $methodePaiement,
                numeroClient: $telephone
            ));
        } catch (\RuntimeException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'message' => 'Erreur technique lors de la création du paiement.'
            ], 500);
        }

        $session->remove('panier');

        return $this->json([
            'ok' => true,
            'message' => 'Demande de paiement envoyée. Confirmez sur votre téléphone.',
            'reference' => $commande->getReference(),
            'country' => $country,
            'status' => $commande->getStatut(),
            'processing' => $commande->isProcessing(),
            'poll_url' => $this->generateUrl('api.commande.statut', ['reference' => $commande->getReference()]),
            'instructions_url' => $this->generateUrl('achat.instructions', ['reference' => $commande->getReference()]),
            'confirmation_url' => $this->generateUrl('achat.confirmation', ['transactionId' => $commande->getReference()]),
        ], 201);
    }

    #[Route('/mes-commandes', name: 'achat.commandes')]
    #[IsGranted('ROLE_CLIENT')]
    public function mesCommandes(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth.login');
        }
        $commandes = $this->commandeRepository->findByClient($user);
        $pending = array_filter($commandes, fn ($c) => $c->isPending() && !$c->estExpiree());
        $paid = array_filter($commandes, fn ($c) => $c->isPaid());
        $expired = array_filter($commandes, fn ($c) => $c->isExpired());
        $rejected = array_filter($commandes, fn ($c) => $c->isRejected());
        return $this->render('achat/mes_commandes.html.twig', [
            'pending' => $pending,
            'paid' => $paid,
            'expired' => $expired,
            'rejected' => $rejected,
        ]);
    }

    #[Route('/achat/instructions/{reference}', name: 'achat.instructions', requirements: ['reference' => '[A-Z0-9\-]+'])]
    #[IsGranted('ROLE_CLIENT')]
    public function instructions(string $reference): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth.login');
        }

        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande || $commande->getClient()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('achat/instructions_paiement.html.twig', [
            'commande' => $commande,
            'momoNumero' => $this->momoNumero,
            'momoBeneficiaire' => $this->momoBeneficiaire,
        ]);
    }

    #[Route('/api/commande/statut/{reference}', name: 'api.commande.statut', requirements: ['reference' => '[A-Z0-9\-]+'], methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function statutCommande(string $reference): Response
    {
        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande) {
            return $this->json(['statut' => 'NotFound'], 404);
        }
        $user = $this->getUser();
        if (!$user || $commande->getClient()->getId() !== $user->getId()) {
            return $this->json(['statut' => 'Forbidden'], 403);
        }
        return $this->json(['statut' => $commande->getStatut()]);
    }

    #[Route('/achat/notifier-paiement/{reference}', name: 'achat.notifier_paiement', requirements: ['reference' => '[A-Z0-9\-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function notifierPaiement(string $reference, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth.login');
        }

        $commande = $this->commandeRepository->findByReference($reference);
        if (!$commande || $commande->getClient()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Commande non trouvée');
        }
        if (($commande->getReferenceTransactionClient() ?? '') !== '') {
            $this->addFlash('info', 'Votre référence est déjà envoyée et en cours de traitement. Vous ne pouvez plus la modifier.');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }

        if (!$this->isCsrfTokenValid('notifier_paiement_' . $reference, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée, veuillez réessayer.');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }

        $transactionRef = preg_replace('/\s+/', '', trim((string) $request->request->get('transaction_reference', ''))) ?? '';
        $operateur = trim((string) $request->request->get('operateur', ''));
        if ($transactionRef === '') {
            $this->addFlash('error', 'Veuillez saisir la référence de transaction envoyée par votre opérateur.');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }
        if (!preg_match('/^[A-Za-z0-9\-_\/\.\:]{4,64}$/', $transactionRef)) {
            $this->addFlash('error', 'Référence SMS invalide. Utilisez lettres, chiffres, tirets, slash, underscore, point ou deux-points.');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }
        if (strcasecmp($transactionRef, $commande->getReference()) === 0) {
            $this->addFlash('error', 'Veuillez saisir la référence SMS reçue de votre opérateur (et non la référence commande).');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }
        if ($this->commandeRepository->isTransactionReferenceAlreadyUsed($transactionRef, $commande->getId())) {
            $this->addFlash('error', 'Cette référence SMS est déjà utilisée pour une autre commande. Vérifiez le SMS reçu.');
            return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
        }

        $destinataires = [];
        foreach ($commande->getLignes() as $ligne) {
            $organisateur = $ligne->getEvenement()?->getOrganisateur();
            $email = $organisateur?->getEmail();
            if ($email && !isset($destinataires[$email])) {
                $destinataires[$email] = [
                    'email' => $email,
                    'telephone' => $organisateur?->getTelephone(),
                    'evenement' => $ligne->getEvenement()?->getNom(),
                ];
            }
        }

        $commande->setReferenceTransactionClient($transactionRef);
        // Donner un feedback immédiat côté client après envoi de référence.
        if ($commande->isPending()) {
            $commande->setStatut(Commande::STATUT_PROCESSING);
        }
        // Laisser le temps à l'organisateur de traiter la demande après soumission client.
        $minimumDeadline = (new \DateTimeImmutable())->modify('+30 minutes');
        if (($commande->getDateExpiration() ?? $minimumDeadline) < $minimumDeadline) {
            $commande->setDateExpiration($minimumDeadline);
        }
        $this->entityManager->persist($commande);

        $log = new LogSecurite();
        $log->setAction('CLIENT_REFERENCE_PAIEMENT_ENVOYEE');
        $log->setReferenceCommande($commande->getReference());
        $log->setUtilisateur($user);
        $log->setIpAddress($request->getClientIp());
        $log->setDetails(sprintf(
            'Client: %s | Operateur: %s | Reference transaction: %s',
            $user->getEmail(),
            $operateur !== '' ? $operateur : 'Non précisé',
            $transactionRef
        ));
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        if (count($destinataires) > 0) {
            $this->messageBus->dispatch(new PaymentReferenceNotificationMessage(
                commandeReference: $commande->getReference(),
                montantTotal: $commande->getMontantTotal(),
                clientNom: (string) $user->getNom(),
                clientEmail: (string) $user->getEmail(),
                clientTelephone: (string) $commande->getNumeroClient(),
                operateur: $operateur !== '' ? $operateur : 'Non précisé',
                referenceTransactionClient: $transactionRef,
                destinataires: array_values($destinataires)
            ));
        }

        $this->addFlash('success', 'Référence envoyée avec succès. Nous traitons votre demande sous environ 5 minutes. Vous ne pouvez plus la ressaisir.');

        return $this->redirectToRoute('achat.instructions', ['reference' => $reference]);
    }

    #[Route('/achat/confirmation/{transactionId}', name: 'achat.confirmation', requirements: ['transactionId' => '[A-Za-z0-9_\-\.]+'])]
    #[IsGranted('ROLE_CLIENT')]
    public function confirmation(string $transactionId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth.login');
        }

        $billets = $this->entityManager->getRepository(Billet::class)->findBy([
            'transactionId' => $transactionId,
            'client' => $user,
        ]);
        if (empty($billets)) {
            $commande = $this->commandeRepository->findByReference($transactionId);
            if ($commande && $commande->getClient()->getId() === $user->getId()) {
                if ($commande->isPending()) {
                    $this->addFlash('warning', 'Votre paiement est en attente de validation.');
                    return $this->redirectToRoute('achat.instructions', ['reference' => $transactionId]);
                }
                if ($commande->isExpired()) {
                    $this->addFlash('error', 'Cette commande a expiré.');
                    return $this->redirectToRoute('panier.index');
                }
            }
            throw $this->createNotFoundException('Transaction non trouvée');
        }

        $total = array_sum(array_map(fn (Billet $b) => $b->getPrix(), $billets));

        return $this->render('achat/confirmation.html.twig', [
            'transactionId' => $transactionId,
            'billets' => $billets,
            'total' => $total,
        ]);
    }

    #[Route('/achat/billet/{qrCode}', name: 'achat.billet')]
    #[IsGranted('ROLE_CLIENT')]
    public function billet(string $qrCode): Response
    {
        $billet = $this->entityManager->getRepository(Billet::class)->findOneBy(['qrCode' => $qrCode]);
        if (!$billet) {
            throw $this->createNotFoundException('Billet non trouvé');
        }
        return $this->render('achat/billet.html.twig', ['billet' => $billet]);
    }

    private function isTelephoneCompatibleWithMethod(string $methodePaiement, string $telephone): bool
    {
        $digits = preg_replace('/\D+/', '', $telephone) ?? '';
        if (!str_starts_with($digits, '235')) {
            return false;
        }

        $local = substr($digits, 3);
        if (strlen($local) !== 8) {
            return false;
        }

        $method = strtolower($methodePaiement);
        if ($method === 'airtel') {
            return str_starts_with($local, '6');
        }
        if ($method === 'momo' || $method === 'moov') {
            return str_starts_with($local, '9');
        }

        return false;
    }
}
