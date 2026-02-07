<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EvenementController extends AbstractController
{
    #[Route('/evenements', name: 'evenement.index')]
    public function index(): Response
    {
        $evenements = [
            [
                'id' => 1,
                'slug' => 'concert-live',
                'titre' => 'Concert Live',
                'ville' => 'N\'Djamena',
                'date' => '2026-02-14 20:00',
                'prix_simple' => 3000,
                'prix_vip' => 10000,
            ],
            [
                'id' => 2,
                'slug' => 'match-de-foot',
                'titre' => 'Match de foot',
                'ville' => 'Moundou',
                'date' => '2026-02-18 16:00',
                'prix_simple' => 2000,
                'prix_vip' => 7000,
            ],
            [
                'id' => 3,
                'slug' => 'soiree-urbaine',
                'titre' => 'Soirée Urbaine',
                'ville' => 'Sarh',
                'date' => '2026-02-22 21:30',
                'prix_simple' => 2500,
                'prix_vip' => 9000,
            ],
        ];

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route(
        '/evenements/{slug}-{id}',
        name: 'evenement.show',
        requirements: [
            'id' => '\\d+',
            'slug' => '[a-z0-9-]+'
        ]
    )]
    public function show(string $slug, int $id): Response
    {
        $evenement = [
            'id' => $id,
            'slug' => $slug,
            'titre' => 'Événement #' . $id,
            'description' => 'Description provisoire. Cette page est un prototype frontend (Twig) en attente de la logique backend.',
            'lieu' => 'Ville + Adresse précise',
            'date' => '2026-02-14 20:00',
            'types_billets' => [
                ['code' => 'SIMPLE', 'prix' => 3000],
                ['code' => 'VIP', 'prix' => 10000],
            ],
        ];

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }
}
