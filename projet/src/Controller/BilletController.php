<?php

namespace App\Controller;

use App\Application\Query\ObtenirMesBilletsQuery;
use App\Application\Handler\ObtenirMesBilletsHandler;
use App\Repository\BilletRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BilletController extends AbstractController
{
    public function __construct(
        private BilletRepository $billetRepository,
        private ObtenirMesBilletsHandler $obtenirMesBilletsHandler
    ) {
    }

    #[Route('/mes-billets', name: 'billet.index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // ✅ CQRS Query : Obtenir les billets
        $query = new ObtenirMesBilletsQuery(userId: $user->getId());
        $billets = $this->obtenirMesBilletsHandler->handle($query);
        
        // Grouper par événement pour meilleure organisation
        $billetsByEvenement = [];
        $totalAmount = 0;
        
        foreach ($billets as $billet) {
            $evenementId = $billet->getEvenement()->getId();
            if (!isset($billetsByEvenement[$evenementId])) {
                $billetsByEvenement[$evenementId] = [
                    'evenement' => $billet->getEvenement(),
                    'billets' => [],
                    'total' => 0
                ];
            }
            $billetsByEvenement[$evenementId]['billets'][] = $billet;
            $billetsByEvenement[$evenementId]['total'] += $billet->getPrix();
            $totalAmount += $billet->getPrix();
        }

        return $this->render('billet/index.html.twig', [
            'billetsByEvenement' => $billetsByEvenement,
            'totalBillets' => count($billets),
            'totalAmount' => $totalAmount
        ]);
    }

    #[Route('/mes-billets/avenir', name: 'billet.avenir')]
    #[IsGranted('ROLE_USER')]
    public function billetsAVenir(): Response
    {
        $user = $this->getUser();
        
        // ✅ CQRS Query : Billets à venir
        $query = new ObtenirMesBilletsQuery(userId: $user->getId(), filtre: 'avenir');
        $billets = $this->obtenirMesBilletsHandler->handle($query);
        
        return $this->render('billet/avenir.html.twig', [
            'billets' => $billets
        ]);
    }

    #[Route('/mes-billets/passes', name: 'billet.passes')]
    #[IsGranted('ROLE_USER')]
    public function billetsPasses(): Response
    {
        $user = $this->getUser();
        
        // ✅ CQRS Query : Billets passés
        $query = new ObtenirMesBilletsQuery(userId: $user->getId(), filtre: 'passes');
        $billets = $this->obtenirMesBilletsHandler->handle($query);
        
        return $this->render('billet/passes.html.twig', [
            'billets' => $billets
        ]);
    }
}
