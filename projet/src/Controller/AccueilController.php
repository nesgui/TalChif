<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    #[Route('/', name: 'accueil')]
    public function index(): Response
    {
        // Récupérer les événements actifs pour la page d'accueil
        $evenements = $this->evenementRepository->findActiveEvents(6);

        // Transformer les entités en tableaux avec badges
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
                'note' => $this->generateRandomNote(), // TODO: Implémenter vrai système d'avis
                'avis' => rand(10, 200), // TODO: Compter les vrais avis
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
        // Événement complet
        if ($evenement->getPlacesRestantes() === 0) {
            return 'Complet';
        }

        // Meilleure vente (plus de 50 places vendues)
        if ($evenement->getPlacesVendues() > 50) {
            return 'Meilleure vente';
        }

        // Nouveau (créé il y a moins de 7 jours)
        if ($evenement->getCreatedAt() > new \DateTimeImmutable('-7 days')) {
            return 'Nouveau';
        }

        // Par défaut: recommandé
        return 'Recommandé';
    }

    private function generateRandomNote(): float
    {
        // Générer une note entre 3.5 et 5.0 avec 1 décimale
        return round(3.5 + (rand(0, 15) / 10), 1);
    }
}
