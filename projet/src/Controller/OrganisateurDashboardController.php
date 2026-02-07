<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrganisateurDashboardController extends AbstractController
{
    #[Route('/organisateur', name: 'organisateur.dashboard')]
    public function index(): Response
    {
        return $this->render('organisateur_dashboard/index.html.twig');
    }
}
