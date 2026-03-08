<?php

namespace App\Controller;

use App\Application\Command\CreerCommandeCommand;
use App\Application\Handler\CreerCommandeHandler;
use App\Entity\Billet;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        #[Autowire('%app.momo.numero%')]
        private string $momoNumero,
        #[Autowire('%app.momo.beneficiaire%')]
        private string $momoBeneficiaire
    ) {
    }

    #[Route('/achat', name: 'achat.index')]
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

    #[Route('/mes-commandes', name: 'achat.commandes')]
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

    #[Route('/achat/confirmation/{transactionId}', name: 'achat.confirmation', requirements: ['transactionId' => '[A-Za-z0-9_\-\.]+'])]
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
    public function billet(string $qrCode): Response
    {
        $billet = $this->entityManager->getRepository(Billet::class)->findOneBy(['qrCode' => $qrCode]);
        if (!$billet) {
            throw $this->createNotFoundException('Billet non trouvé');
        }
        return $this->render('achat/billet.html.twig', ['billet' => $billet]);
    }
}
