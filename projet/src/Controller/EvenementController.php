<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EvenementController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    #[Route('/evenements', name: 'evenement.index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q');

        if ($search) {
            $evenements = $this->evenementRepository->searchEvents($search);
        } else {
            $evenements = $this->evenementRepository->findActiveEvents();
        }

        // Transformer les entités en tableau pour le template existant
        $evenementsArray = [];
        foreach ($evenements as $evenement) {
            $evenementsArray[] = [
                'id' => $evenement->getId(),
                'slug' => $evenement->getSlug(),
                'titre' => $evenement->getNom(),
                'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
                'ville' => $evenement->getVille(),
                'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                'prix_simple' => $evenement->getPrixSimple(),
                'prix_vip' => $evenement->getPrixVip(),
                'prix_min' => $evenement->getPrixSimple(),
                'note' => 4.5, // TODO: Implémenter système d'avis
                'avis' => 0, // TODO: Compter les vrais avis
                'badge' => $this->getBadgeForEvent($evenement),
            ];
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementsArray,
        ]);
    }

    #[Route(
        '/evenements/{slug}-{id}',
        name: 'evenement.show',
        requirements: [
            'id' => '\d+',
            'slug' => '[a-z0-9-]+'
        ]
    )]
    public function show(string $slug, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);

        if (!$evenement || $evenement->getSlug() !== $slug) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        // Transformer l'entité en tableau pour le template existant
        $evenementArray = [
            'id' => $evenement->getId(),
            'slug' => $evenement->getSlug(),
            'titre' => $evenement->getNom(),
            'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/default.svg',
            'description' => $evenement->getDescription(),
            'lieu' => $evenement->getLieu(),
            'adresse' => $evenement->getAdresse(),
            'ville' => $evenement->getVille(),
            'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
            'prix_min' => $evenement->getPrixSimple(),
            'note' => 4.5, // TODO: Implémenter système d'avis
            'avis' => 0, // TODO: Compter les vrais avis
            'badge' => $this->getBadgeForEvent($evenement),
            'places_disponibles' => $evenement->getPlacesRestantes(),
            'places_total' => $evenement->getPlacesDisponibles(),
            'organisateur' => $evenement->getOrganisateur()->getFullName(),
            'types_billets' => $this->getTicketTypes($evenement),
        ];

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenementArray,
        ]);
    }

    #[Route('/evenements/{slug}', name: 'evenement.show.redirect')]
    public function showRedirect(string $slug): Response
    {
        error_log('DEBUG: showRedirect called with slug: ' . $slug);

        // Chercher l'événement par slug
        $evenement = $this->evenementRepository->findOneBy(['slug' => $slug]);

        if (!$evenement) {
            error_log('DEBUG: Event not found for slug: ' . $slug);
            // Lister tous les slugs disponibles pour debug
            $allEvents = $this->evenementRepository->findAll();
            error_log('DEBUG: Available slugs:');
            foreach ($allEvents as $e) {
                error_log('DEBUG:  - ID ' . $e->getId() . ' -> ' . $e->getSlug());
            }
            throw $this->createNotFoundException('Événement non trouvé');
        }

        error_log('DEBUG: Found event, redirecting to: ' . $evenement->getSlug() . '-' . $evenement->getId());

        // Rediriger vers l'URL correcte
        return $this->redirectToRoute('evenement.show', [
            'slug' => $evenement->getSlug(),
            'id' => $evenement->getId()
        ]);
    }

    private function getBadgeForEvent(Evenement $evenement): ?string
    {
        if ($evenement->isComplet()) {
            return 'Complet';
        }

        if ($evenement->getPlacesVendues() > 50) {
            return 'Meilleure vente';
        }

        if ($evenement->getCreatedAt() > new \DateTimeImmutable('-7 days')) {
            return 'Nouveau';
        }

        return 'Recommandé';
    }

    private function getTicketTypes(Evenement $evenement): array
    {
        $types = [
            ['code' => 'SIMPLE', 'prix' => $evenement->getPrixSimple()],
        ];

        if ($evenement->hasVip()) {
            $types[] = ['code' => 'VIP', 'prix' => $evenement->getPrixVip()];
        }

        return $types;
    }
}
