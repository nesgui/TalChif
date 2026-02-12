<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use App\Service\Payment\PaymentInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AchatController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private EntityManagerInterface $entityManager,
        private PaymentInterface $paymentService
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
        $total = 0;

        foreach ($panier as $id => $quantite) {
            $evenement = $this->evenementRepository->find($id);
            
            if (!$evenement || !$evenement->isIsActive()) {
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

        return $this->render('achat/index_test.html.twig', [
            'lignes' => $lignes,
            'total' => $total,
        ]);
    }

    #[Route(
        '/evenements/{slug}-{id}/achat',
        name: 'achat.evenement',
        requirements: [
            'id' => '\\d+',
            'slug' => '[a-z0-9-]+'
        ]
    )]
    public function achatEvenement(string $slug, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);

        if (!$evenement) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        // Redirection si l'URL contient un slug obsolète (après modification du nom)
        if ($evenement->getSlug() !== $slug) {
            return $this->redirectToRoute('achat.evenement', [
                'slug' => $evenement->getSlug(),
                'id' => $evenement->getId(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        if (!$evenement->isIsActive()) {
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
            ]
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
        
        if (!$this->paymentService->supports($methodePaiement ?? '') || empty($telephone)) {
            $this->addFlash('error', 'Informations de paiement invalides');
            return $this->redirectToRoute('achat.index');
        }

        $total = 0;
        $lignes = [];

        try {
            foreach ($panier as $id => $quantite) {
                $evenement = $this->evenementRepository->find($id);
                if (!$evenement || !$evenement->isIsActive()) {
                    continue;
                }
                if ($quantite > $evenement->getPlacesRestantes()) {
                    throw new \Exception("Plus assez de places disponibles pour {$evenement->getNom()}");
                }
                $total += $evenement->getPrixSimple() * $quantite;
                $lignes[] = ['evenement' => $evenement, 'quantite' => $quantite];
            }

            if ($total <= 0) {
                $this->addFlash('error', 'Panier invalide ou événements indisponibles.');
                return $this->redirectToRoute('achat.index');
            }

            $result = $this->paymentService->payer($total, $methodePaiement, [
                'telephone' => $telephone,
                'email' => $user->getUserIdentifier(),
            ]);

            if (!$result->isSuccess()) {
                $this->addFlash('error', 'Paiement refusé : ' . $result->getMessage());
                return $this->redirectToRoute('achat.index');
            }

            $transactionId = $result->getTransactionId();

            foreach ($lignes as $ligne) {
                $evenement = $ligne['evenement'];
                $quantite = $ligne['quantite'];

                for ($i = 0; $i < $quantite; $i++) {
                    $billet = new Billet();
                    $billet->setQrCode($this->generateQrCode());
                    $billet->setType('SIMPLE');
                    $billet->setPrix($evenement->getPrixSimple());
                    $billet->setEvenement($evenement);
                    $billet->setClient($user);
                    $billet->setOrganisateur($evenement->getOrganisateur());
                    $billet->setTransactionId($transactionId);
                    $billet->setStatutPaiement('PAYE');
                    $billet->validerPaiement();
                    $this->entityManager->persist($billet);
                }

                $evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
            }

            $this->entityManager->flush();

            $this->addFlash('success', "📱 SMS de confirmation envoyé au {$telephone}");
            $this->addFlash('success', "💳 {$result->getMessage()}");
            $this->addFlash('info', '🎫 Vos billets ont été générés avec succès.');
            $session->remove('panier');

            return $this->redirectToRoute('achat.confirmation', [
                'transactionId' => $transactionId,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('achat.index');
        }
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
            'client' => $user
        ]);

        if (empty($billets)) {
            throw $this->createNotFoundException('Transaction non trouvée');
        }

        return $this->render('achat/confirmation.html.twig', [
            'transactionId' => $transactionId,
            'billets' => $billets,
            'total' => array_sum(array_map(fn($b) => $b->getPrix(), $billets))
        ]);
    }

    #[Route('/achat/billet/{qrCode}', name: 'achat.billet')]
    public function billet(string $qrCode): Response
    {
        $billet = $this->entityManager->getRepository(Billet::class)->findOneBy(['qrCode' => $qrCode]);
        
        if (!$billet) {
            throw $this->createNotFoundException('Billet non trouvé');
        }

        return $this->render('achat/billet.html.twig', [
            'billet' => $billet
        ]);
    }

    private function generateQrCode(): string
    {
        return 'BILLET_' . uniqid() . '_' . time();
    }
}
