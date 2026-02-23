<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPagesController extends AbstractController
{
    #[Route('/moi', name: 'public.me')]
    public function me(): Response
    {
        return $this->render('public/me.html.twig');
    }

    #[Route('/infos', name: 'public.infos')]
    public function infos(): Response
    {
        return $this->render('public/infos.html.twig');
    }

    #[Route('/devenir-organisateur', name: 'public.devenir_organisateur')]
    public function devenirOrganisateur(): Response
    {
        return $this->render('public/devenir_organisateur.html.twig');
    }

    #[Route('/a-propos', name: 'public.a_propos')]
    public function aPropos(): Response
    {
        return $this->render('public/a_propos.html.twig');
    }
}
