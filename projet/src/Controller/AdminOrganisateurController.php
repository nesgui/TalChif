<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminOrganisateurController extends AbstractController
{
    #[Route('/admin/organisateurs', name: 'admin.organisateur.index')]
    public function index(): Response
    {
        return $this->render('admin_organisateur/index.html.twig');
    }
}
