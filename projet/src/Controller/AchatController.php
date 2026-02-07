<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AchatController extends AbstractController
{
    #[Route('/achat', name: 'achat.index')]
    public function index(): Response
    {
        return $this->render('achat/index.html.twig', [
            'controller_name' => 'AchatController',
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
        return $this->render('achat/evenement.html.twig', [
            'slug' => $slug,
            'id' => $id,
        ]);
    }
}
