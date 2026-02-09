<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BilletController extends AbstractController
{
    public function __construct(
        private BilletRepository $billetRepository
    ) {
    }

    #[Route('/mes-billets', name: 'billet.index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer tous les billets de l'utilisateur
        $billets = $this->billetRepository->findBy(['client' => $user], ['createdAt' => 'DESC']);
        
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
        
        // Récupérer les billets des événements à venir
        $billets = $this->billetRepository->findBilletsAVenir($user);
        
        return $this->render('billet/avenir.html.twig', [
            'billets' => $billets
        ]);
    }

    #[Route('/mes-billets/passes', name: 'billet.passes')]
    #[IsGranted('ROLE_USER')]
    public function billetsPasses(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les billets des événements passés
        $billets = $this->billetRepository->findBilletsPasses($user);
        
        return $this->render('billet/passes.html.twig', [
            'billets' => $billets
        ]);
    }
}
