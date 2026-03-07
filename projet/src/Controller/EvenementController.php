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
        // Limiter longueur et rejeter caractères de contrôle pour éviter données invalides
        if ($search !== null && $search !== '') {
            $search = trim($search);
            $search = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $search);
            $search = mb_substr($search, 0, 200);
        }
        if ($search !== null && $search !== '') {
            $evenements = $this->evenementRepository->searchEvents($search);
        } else {
            $evenements = $this->evenementRepository->findActiveEvents();
        }

        // Transformer les entités en tableau pour le template existant
        $evenementsArray = [];
        $nouveauxEvenements = [];
        $plusAchetes = [];
        $evenementsPopulaires = [];
        
        foreach ($evenements as $evenement) {
            $eventData = [
                'id' => $evenement->getId(),
                'slug' => $evenement->getSlug(),
                'titre' => $evenement->getNom(),
                'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/evenement-1.jpg',
                'ville' => $evenement->getVille(),
                'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
                'prix_simple' => $evenement->getPrixSimple(),
                'prix_vip' => $evenement->getPrixVip(),
                'prix_min' => $evenement->getPrixSimple(),
                'note' => null,
                'avis' => null,
                'badge' => $this->getBadgeForEvent($evenement),
                'places_vendues' => $evenement->getPlacesVendues(),
                'created_at' => $evenement->getCreatedAt(),
                'categorie' => $evenement->getCategorie(),
            ];
            
            $evenementsArray[] = $eventData;
            
            // Catégoriser les événements
            if ($eventData['badge'] === 'Nouveau') {
                $nouveauxEvenements[] = $eventData;
            }
            
            // Plus achetés (basé sur les places vendues)
            if ($eventData['places_vendues'] > 10) {
                $plusAchetes[] = $eventData;
            }
            
            // Événements populaires (vendues > 5 ou récents)
            if ($eventData['places_vendues'] > 5 || $eventData['badge'] === 'Recommandé') {
                $evenementsPopulaires[] = $eventData;
            }
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementsArray,
            'nouveaux_evenements' => $nouveauxEvenements,
            'plus_achetes' => $plusAchetes,
            'evenements_populaires' => $evenementsPopulaires,
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

        if (!$evenement) {
            // Si l'id ne correspond à rien, tenter par slug (URL avec mauvais id mais bon slug)
            $evenementBySlug = $this->evenementRepository->findOneBy(['slug' => $slug]);
            if ($evenementBySlug) {
                return $this->redirectToRoute('evenement.show', [
                    'slug' => $evenementBySlug->getSlug(),
                    'id' => $evenementBySlug->getId(),
                ], Response::HTTP_MOVED_PERMANENTLY);
            }
            throw $this->createNotFoundException('Événement non trouvé');
        }

        // Redirection canonique : si le slug en URL ne correspond plus (ex. après modification du nom),
        // rediriger vers l'URL correcte au lieu de renvoyer une 404.
        if ($evenement->getSlug() !== $slug) {
            return $this->redirectToRoute('evenement.show', [
                'slug' => $evenement->getSlug(),
                'id' => $evenement->getId(),
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        // Transformer l'entité en tableau pour le template existant
        $evenementArray = [
            'id' => $evenement->getId(),
            'slug' => $evenement->getSlug(),
            'titre' => $evenement->getNom(),
            'image' => $evenement->getAffichePrincipale() ?: '/images/evenements/evenement-1.jpg',
            'description' => $evenement->getDescription(),
            'lieu' => $evenement->getLieu(),
            'adresse' => $evenement->getAdresse(),
            'ville' => $evenement->getVille(),
            'date' => $evenement->getDateEvenement()->format('Y-m-d H:i'),
            'prix_min' => $evenement->getPrixSimple(),
            'note' => null,
            'avis' => null,
            'badge' => $this->getBadgeForEvent($evenement),
            'places_disponibles' => $evenement->getPlacesRestantes(),
            'places_total' => $evenement->getPlacesDisponibles(),
            // Nombre de billets déjà vendus (pour la social proof)
            'ventes' => $evenement->getPlacesVendues(),
            'organisateur' => $evenement->getOrganisateur() ? $evenement->getOrganisateur()->getFullName() : 'Non spécifié',
            'types_billets' => $this->getTicketTypes($evenement),
            'categorie' => $evenement->getCategorie(),
        ];

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenementArray,
        ]);
    }

    /**
     * Redirection depuis une URL avec slug seul vers l'URL canonique (slug-id).
     */
    #[Route('/evenements/{slug}', name: 'evenement.show.redirect')]
    public function showRedirect(string $slug): Response
    {
        $evenement = $this->evenementRepository->findOneBy(['slug' => $slug]);
        if (!$evenement) {
            throw $this->createNotFoundException('Événement non trouvé');
        }
        return $this->redirectToRoute('evenement.show', [
            'slug' => $evenement->getSlug(),
            'id' => $evenement->getId(),
        ]);
    }

    private function getBadgeForEvent(Evenement $evenement): ?string
    {
        if ($evenement->isComplet()) {
            return 'Complet';
        }

        $seuil = $this->getParameter('app.badge.meilleure_vente_seuil');
        if ($evenement->getPlacesVendues() > $seuil) {
            return 'Meilleure vente';
        }
        $jours = (int) $this->getParameter('app.badge.nouveau_jours');
        if ($evenement->getCreatedAt() > new \DateTimeImmutable("-{$jours} days")) {
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
