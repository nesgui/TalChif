<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrganisateurEvenementController extends AbstractController
{
    #[Route('/organisateur/evenements', name: 'organisateur.evenement.index')]
    public function index(): Response
    {
        return $this->render('organisateur_evenement/index.html.twig', [
            'controller_name' => 'OrganisateurEvenementController',
        ]);
    }

    #[Route('/organisateur/evenements/{id}', name: 'organisateur.evenement.show', requirements: ['id' => '\\d+'])]
    public function show(int $id): Response
    {
        return $this->render('organisateur_evenement/show.html.twig', [
            'id' => $id,
        ]);
    }
}
