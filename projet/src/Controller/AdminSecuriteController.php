<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSecuriteController extends AbstractController
{
    #[Route('/admin/securite', name: 'admin.securite.index')]
    public function index(): Response
    {
        return $this->render('admin_securite/index.html.twig');
    }
}
