<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PortefeuilleController extends AbstractController
{
    #[Route('/portefeuille', name: 'portefeuille.index')]
    #[IsGranted('ROLE_CLIENT')]
    public function index(): Response
    {
        return $this->render('portefeuille/index.html.twig');
    }
}
