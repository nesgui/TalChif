<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Service\Panier\ResolveurPanier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion du panier (session) : affichage, ajout, modification quantite, suppression.
 * Toutes les actions modifiant le panier sont protegees par un jeton CSRF.
 */
final class PanierController extends AbstractController
{
    private const TYPE_SIMPLE = 'SIMPLE';
    private const TYPE_VIP = 'VIP';

    /** @var string[] */
    private const TYPES_BILLET = [self::TYPE_SIMPLE, self::TYPE_VIP];

    public function __construct(
        private EvenementRepository $evenementRepository,
        private ResolveurPanier $resolveurPanier
    ) {
    }

    #[Route('/panier', name: 'panier.index', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        $contenu = $this->resolveurPanier->resoudre($session);

        return $this->render('panier/index.html.twig', [
            'lignes' => $contenu->lignes,
            'total' => $contenu->total,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'panier.ajouter', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function ajouter(int $id, Request $request, SessionInterface $session): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('panier_ajouter', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide. Veuillez reessayer.');

            return $this->redirectToRoute('panier.index');
        }

        $evenement = $this->evenementRepository->find($id);

        if (!$evenement || !$evenement->isActive()) {
            $this->addFlash('error', 'Evenement non disponible');

            return $this->redirectToRoute('evenement.index');
        }

        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Cet evenement est complet');

            return $this->redirectToRoute('evenement.show', ['slug' => $evenement->getSlug(), 'id' => $evenement->getId()]);
        }

        $quantite = (int) $request->request->get('quantite', 1);
        $quantite = max(1, min($quantite, $evenement->getPlacesRestantes()));
        $type = $this->normaliserType($request->request->get('type'));

        $panier = $session->get('panier', []);

        // Structure : [id_evenement => ['quantite' => int, 'type' => string]]
        $panier[$id] = [
            'quantite' => ($panier[$id]['quantite'] ?? 0) + $quantite,
            'type' => $type,
        ];
        $session->set('panier', $panier);

        $this->addFlash('success', 'Evenement ajoute au panier');

        $redirect = $request->request->get('redirect');
        if ($redirect === 'achat') {
            return $this->redirectToRoute('achat.index');
        }
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
        if (!$evenement || !$evenement->isActive()) {
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
            // Préserver le type existant si nouvelle structure
            if (isset($panier[$id]) && is_array($panier[$id])) {
                $panier[$id]['quantite'] = $quantite;
            } else {
                // Ancienne structure ou nouvel article
                $panier[$id] = [
                    'quantite' => $quantite,
                    'type' => 'SIMPLE'
                ];
            }
            $this->addFlash('success', 'Quantité mise à jour');
        }

        $session->set('panier', $panier);
        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/supprimer/{id}', name: 'panier.supprimer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function supprimer(int $id, Request $request, SessionInterface $session): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('panier_supprimer_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('panier.index');
        }

        $panier = $session->get('panier', []);
        if (isset($panier[$id])) {
            unset($panier[$id]);
            $session->set('panier', $panier);
            $this->addFlash('success', 'Article supprimé du panier');
        }

        return $this->redirectToRoute('panier.index');
    }

    #[Route('/panier/vider', name: 'panier.vider', methods: ['POST'])]
    public function vider(Request $request, SessionInterface $session): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('panier_vider', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de securite invalide. Veuillez reessayer.');

            return $this->redirectToRoute('panier.index');
        }

        $session->remove('panier');
        $this->addFlash('success', 'Panier vide');

        return $this->redirectToRoute('panier.index');
    }

    /**
     * Les types de billet sont normalises en majuscules : le calcul de prix
     * compare strictement a « VIP » cote commande.
     */
    private function normaliserType(mixed $type): string
    {
        $normalise = strtoupper(trim((string) $type));

        return \in_array($normalise, self::TYPES_BILLET, true) ? $normalise : self::TYPE_SIMPLE;
    }
}
