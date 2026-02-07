<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminEvenementController extends AbstractController
{
    #[Route('/admin/evenements', name: 'admin.evenement.index')]
    public function index(): Response
    {
        return $this->render('admin_evenement/index.html.twig');
    }
}
