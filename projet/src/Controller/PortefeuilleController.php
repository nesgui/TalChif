<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PortefeuilleController extends AbstractController
{
    public function __construct(
        private BilletRepository $billetRepository,
        private CommandeRepository $commandeRepository,
    ) {}

    #[Route('/portefeuille', name: 'portefeuille.index')]
    #[IsGranted('ROLE_CLIENT')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('portefeuille/index.html.twig', [
            'billets_a_venir' => $this->billetRepository->findBilletsAVenir($user),
            'billets_passes'  => array_slice($this->billetRepository->findBilletsPasses($user), 0, 3),
            'commandes_pending' => array_filter(
                $this->commandeRepository->findByClient($user),
                fn($c) => $c->isPending() && !$c->estExpiree()
            ),
            'profileIncomplete' => method_exists($user, 'isProfileComplete') ? !$user->isProfileComplete() : false,
        ]);
    }
}
