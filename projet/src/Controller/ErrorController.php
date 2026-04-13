<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController
{
    #[Route('/error/403', name: 'error.403')]
    public function accessDenied(): Response
    {
        return $this->render('error/403.html.twig');
    }

    public function show(\Throwable $exception): Response
    {
        // Si c'est une erreur d'accès refusé, utiliser notre template personnalisé
        if ($exception instanceof AccessDeniedHttpException) {
            return $this->render('error/403.html.twig');
        }

        // Pour les autres erreurs, utiliser le template par défaut
        return $this->render('error/error.html.twig', [
            'exception' => $exception,
        ]);
    }
}
