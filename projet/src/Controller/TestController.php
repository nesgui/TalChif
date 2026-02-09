<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test/connexion', name: 'test.connexion')]
    public function connexion(): Response
    {
        return $this->render('test/connexion.html.twig');
    }
}
