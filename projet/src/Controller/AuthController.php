<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/connexion', name: 'auth.login')]
    public function login(): Response
    {
        return $this->render('auth/login.html.twig');
    }

    #[Route('/inscription', name: 'auth.register')]
    public function register(): Response
    {
        return $this->render('auth/register.html.twig');
    }
}
