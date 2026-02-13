<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil : affiche les événements actifs (limite et badges via config).
 */
final class AccueilController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    #[Route('/', name: 'accueil')]
    public function index(): Response
    {
        $limite = $this->getParameter('app.accueil.evenements_limite');
        $evenements = $this->evenementRepository->findActiveEvents($limite);

        // Transformer les entités en tableaux pour le template (badges, etc.)
        $evenementsArray = [];
        foreach ($evenements as $evenement) {
            $evenementsArray[] = [
                'id' => $evenement->getId(),
                'slug' => $evenement->getSlug(),
                'titre' => $evenement->getNom(),
                'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
                'description' => $evenement->getDescription(),
                'ville' => $evenement->getVille(),
                'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                'prix_min' => $evenement->getPrixSimple(),
                'note' => null,
                'avis' => null,
                'badge' => $this->getBadgeForEvent($evenement),
                'places_disponibles' => $evenement->getPlacesRestantes(),
                'places_total' => $evenement->getPlacesDisponibles(),
            ];
        }

        return $this->render('accueil/index.html.twig', [
            'evenements' => $evenementsArray,
        ]);
    }

    private function getBadgeForEvent($evenement): string
    {
        if ($evenement->getPlacesRestantes() === 0) {
            return 'Complet';
        }
        $seuilVentes = $this->getParameter('app.badge.meilleure_vente_seuil');
        if ($evenement->getPlacesVendues() > $seuilVentes) {
            return 'Meilleure vente';
        }
        $joursNouveau = (int) $this->getParameter('app.badge.nouveau_jours');
        if ($evenement->getCreatedAt() > new \DateTimeImmutable("-{$joursNouveau} days")) {
            return 'Nouveau';
        }
        return 'Recommandé';
    }
}
