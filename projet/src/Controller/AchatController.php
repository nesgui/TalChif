<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Repository\EvenementRepository;
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
        private EntityManagerInterface $entityManager
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

        if (!$evenement || $evenement->getSlug() !== $slug) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        if (!$evenement->isIsActive()) {
            $this->addFlash('error', 'Cet événement n\'est pas disponible');
            return $this->redirectToRoute('evenement.show', ['slug' => $slug, 'id' => $id]);
        }

        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Cet événement est complet');
            return $this->redirectToRoute('evenement.show', ['slug' => $slug, 'id' => $id]);
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
        
        if (!in_array($methodePaiement, ['momo', 'airtel', 'orange']) || !$telephone) {
            $this->addFlash('error', 'Informations de paiement invalides');
            return $this->redirectToRoute('achat.index');
        }

        // SIMULATION DE PAIEMENT - SANS API RÉELLE
        $transactionId = 'TEST_' . strtoupper($methodePaiement) . '_' . uniqid();
        $total = 0;

        try {
            // Étape 1: Simulation de validation du numéro de téléphone
            if (!$this->validerTelephone($telephone)) {
                throw new \Exception('Format du numéro de téléphone invalide');
            }

            // Étape 2: Simulation de la vérification du solde (toujours réussi en test)
            $this->addFlash('info', "Vérification du solde {$methodePaiement}...");

            // Étape 3: Simulation du processus de paiement
            sleep(1); // Simuler un délai de traitement
            
            foreach ($panier as $id => $quantite) {
                $evenement = $this->evenementRepository->find($id);
                
                if (!$evenement || !$evenement->isIsActive()) {
                    continue;
                }

                // Vérifier les places disponibles
                if ($quantite > $evenement->getPlacesRestantes()) {
                    throw new \Exception("Plus assez de places disponibles pour {$evenement->getNom()}");
                }

                // Créer les billets
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

                // Mettre à jour les places vendues
                $evenement->setPlacesVendues($evenement->getPlacesVendues() + $quantite);
                $total += $evenement->getPrixSimple() * $quantite;
            }

            $this->entityManager->flush();

            // Étape 4: Simulation de confirmation SMS
            $this->addFlash('success', "📱 SMS de confirmation envoyé au {$telephone}");
            $this->addFlash('success', "💳 Paiement de {$total} XAF effectué avec succès par {$methodePaiement} !");
            $this->addFlash('info', "🎫 Vos billets ont été générés avec succès");

            // Vider le panier
            $session->remove('panier');
            
            return $this->redirectToRoute('achat.confirmation', [
                'transactionId' => $transactionId
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du paiement: ' . $e->getMessage());
            return $this->redirectToRoute('achat.index');
        }
    }

    private function validerTelephone(string $telephone): bool
    {
        // Formats acceptés pour le Tchad
        $patterns = [
            '/^235\s\d{2}\s\d{2}\s\d{2}\s\d{2}$/', // 235 XX XX XX XX
            '/^235\d{8}$/',                           // 235XXXXXXXX
            '/^\+235\d{8}$/',                         // +235XXXXXXXX
            '/^00\d{11}$/'                            // 00XXXXXXXXXXX
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, preg_replace('/\s/', '', $telephone))) {
                return true;
            }
        }

        return false;
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
