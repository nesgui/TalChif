<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Repository\EvenementRepository;
use App\Service\Achat\ServiceAchat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur des achats : page panier/checkout, paiement et confirmation.
 * La logique métier (paiement + billets) est déléguée à ServiceAchat (transactions, cohérence).
 */
final class AchatController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private EntityManagerInterface $entityManager,
        private ServiceAchat $serviceAchat
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
            $resultat = $this->serviceAchat->traiterAchat(
                $panier,
                $user,
                (string) $methodePaiement,
                trim((string) $telephone)
            );
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('achat.index');
        }

        $session->remove('panier');
        $this->addFlash('success', '📱 SMS de confirmation envoyé au ' . trim((string) $telephone));
        $this->addFlash('success', '💳 ' . $resultat->getMessagePaiement());
        $this->addFlash('info', '🎫 Vos billets ont été générés avec succès.');

        return $this->redirectToRoute('achat.confirmation', [
            'transactionId' => $resultat->getIdTransaction(),
        ]);
    }

    #[Route('/achat/confirmation/{transactionId}', name: 'achat.confirmation')]
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
