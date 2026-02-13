<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de pages de test (connexion, thème, datatables).
 * Accessible uniquement en environnement "dev" pour éviter l'exposition en production.
 */
final class TestController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.environment')]
        private string $environment
    ) {
    }

    private function refuserSiPasDev(): void
    {
        if ($this->environment !== 'dev') {
            throw $this->createAccessDeniedException('Les routes de test ne sont disponibles qu’en environnement de développement.');
        }
    }

    #[Route('/test/connexion', name: 'test.connexion')]
    public function connexion(): Response
    {
        $this->refuserSiPasDev();
        return $this->render('test/connexion.html.twig');
    }

    #[Route('/test/dashboard-theme', name: 'test.dashboard.theme')]
    public function dashboardTheme(): Response
    {
        $this->refuserSiPasDev();
        return $this->render('test/dashboard_theme.html.twig');
    }

    #[Route('/test/datatables', name: 'test.datatables')]
    public function datatables(): Response
    {
        $this->refuserSiPasDev();
        $this->addFlash('success', 'Test de notification succès');
        $this->addFlash('info', 'Test de notification info');
        $this->addFlash('warning', 'Test de notification warning');
        return $this->render('test/datatables.html.twig');
    }

    #[Route('/test/notifications', name: 'test.notifications')]
    public function notifications(Request $request): Response
    {
        $this->refuserSiPasDev();
        
        // Récupérer le paramètre 'type' de la query string
        $type = $request->query->get('type');
        
        // Si un type est fourni, ajouter un flash message
        if ($type !== null) {
            match($type) {
                'success' => $this->addFlash('success', 'Ceci est un message de succès ! ✅'),
                'error' => $this->addFlash('error', 'Une erreur s\'est produite lors du traitement ❌'),
                'warning' => $this->addFlash('warning', 'Attention, vérifiez vos informations ⚠️'),
                'info' => $this->addFlash('info', 'Information : le système a été mis à jour ℹ️'),
                default => null
            };
        }
        
        return $this->render('test/notifications.html.twig');
    }
}
