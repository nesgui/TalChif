<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PanierController extends AbstractController
{
    #[Route('/panier', name: 'panier.index', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);
        $catalogue = $this->catalogue();

        $lignes = [];
        $total = 0;

        foreach ($panier as $id => $quantite) {
            if (!isset($catalogue[$id])) {
                continue;
            }

            $produit = $catalogue[$id];
            $sousTotal = $produit['prix_min'] * $quantite;
            $total += $sousTotal;

            $lignes[] = [
                'id' => $id,
                'quantite' => $quantite,
                'produit' => $produit,
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
        $catalogue = $this->catalogue();
        if (!isset($catalogue[$id])) {
            return $this->redirectToRoute('panier.index');
        }

        $quantite = (int) $request->request->get('quantite', 1);
        if ($quantite < 1) {
            $quantite = 1;
        }
        if ($quantite > 10) {
            $quantite = 10;
        }

        $panier = $session->get('panier', []);
        $panier[$id] = ($panier[$id] ?? 0) + $quantite;
        $session->set('panier', $panier);

        $redirect = (string) $request->request->get('redirect', 'panier');
        if ($redirect === 'precedent') {
            $referer = $request->headers->get('referer');
            if ($referer) {
                return $this->redirect($referer);
            }
        }

        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/supprimer/{id}', name: 'panier.supprimer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function supprimer(int $id, Request $request, SessionInterface $session): RedirectResponse
    {
        $panier = $session->get('panier', []);
        unset($panier[$id]);
        $session->set('panier', $panier);

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/vider', name: 'panier.vider', methods: ['POST'])]
    public function vider(Request $request, SessionInterface $session): RedirectResponse
    {
        $session->set('panier', []);

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('panier.index');
    }

    private function catalogue(): array
    {
        return [
            1 => [
                'id' => 1,
                'slug' => 'concert-live',
                'titre' => 'Concert Live',
                'image' => '/images/evenements/evenement-1.svg',
                'prix_min' => 3000,
            ],
            2 => [
                'id' => 2,
                'slug' => 'match-de-foot',
                'titre' => 'Match de foot',
                'image' => '/images/evenements/evenement-2.svg',
                'prix_min' => 2000,
            ],
            3 => [
                'id' => 3,
                'slug' => 'soiree-urbaine',
                'titre' => 'Soirée Urbaine',
                'image' => '/images/evenements/evenement-3.svg',
                'prix_min' => 2500,
            ],
        ];
    }
}
