<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PanierController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    #[Route('/panier', name: 'panier.index', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);

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
                    'ville' => $evenement->getVille(),
                    'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                    'places_restantes' => $evenement->getPlacesRestantes(),
                ],
                'sous_total' => $sousTotal,
            ];
        }

        return $this->render('panier/index.html.twig', [
            'lignes' => $lignes,
            'total' => $total,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'panier.ajouter', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function ajouter(int $id, Request $request, SessionInterface $session): RedirectResponse
    {
        $evenement = $this->evenementRepository->find($id);
        
        if (!$evenement || !$evenement->isIsActive()) {
            $this->addFlash('error', 'Événement non disponible');
            return $this->redirectToRoute('evenement.index');
        }

        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Cet événement est complet');
            return $this->redirectToRoute('evenement.show', ['slug' => $evenement->getSlug(), 'id' => $evenement->getId()]);
        }

        $quantite = (int) $request->request->get('quantite', 1);
        $quantite = max(1, min($quantite, $evenement->getPlacesRestantes()));

        $panier = $session->get('panier', []);
        $panier[$id] = ($panier[$id] ?? 0) + $quantite;
        $session->set('panier', $panier);

        $this->addFlash('success', 'Événement ajouté au panier');

        $redirect = $request->request->get('redirect');
        if ($redirect === 'precedent') {
            return $this->redirectToRoute('evenement.show', ['slug' => $evenement->getSlug(), 'id' => $evenement->getId()]);
        }

        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/quantite/{id}', name: 'panier.quantite', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function mettreAJourQuantite(int $id, Request $request, SessionInterface $session): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('panier_quantite', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('panier.index');
        }

        $evenement = $this->evenementRepository->find($id);
        if (!$evenement || !$evenement->isIsActive()) {
            $this->addFlash('error', 'Événement non disponible');
            return $this->redirectToRoute('panier.index');
        }

        $quantite = (int) $request->request->get('quantite', 1);
        $quantite = max(0, min($quantite, $evenement->getPlacesRestantes()));

        $panier = $session->get('panier', []);

        if ($quantite <= 0) {
            unset($panier[$id]);
            $this->addFlash('success', 'Article retiré du panier');
        } else {
            $panier[$id] = $quantite;
            $this->addFlash('success', 'Quantité mise à jour');
        }

        $session->set('panier', $panier);
        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/supprimer/{id}', name: 'panier.supprimer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function supprimer(int $id, SessionInterface $session): RedirectResponse
    {
        $panier = $session->get('panier', []);
        
        if (isset($panier[$id])) {
            unset($panier[$id]);
            $session->set('panier', $panier);
            $this->addFlash('success', 'Article supprimé du panier');
        }

        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/vider', name: 'panier.vider', methods: ['POST'])]
    public function vider(SessionInterface $session): RedirectResponse
    {
        $session->remove('panier');
        $this->addFlash('success', 'Panier vidé');
        return $this->redirectToRoute('panier.index');
    }

    public function getNombreArticles(SessionInterface $session): int
    {
        return array_sum($session->get('panier', []));
    }

    public function getTotal(SessionInterface $session): int
    {
        $panier = $session->get('panier', []);
        $total = 0;

        foreach ($panier as $id => $quantite) {
            $evenement = $this->evenementRepository->find($id);
            if ($evenement && $evenement->isIsActive()) {
                $total += $evenement->getPrixSimple() * $quantite;
            }
        }

        return $total;
    }
}
