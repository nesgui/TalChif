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

        return $this->render('accueil/index.html.twig', [
            'evenements' => $evenements,
        ]);
    }
}
