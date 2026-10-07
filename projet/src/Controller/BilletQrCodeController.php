<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\User;
use App\Service\Ticket\GenerateurImageQrCode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sert l'image PNG du QR Code d'un billet.
 *
 * Point d'entree unique : la generation est faite cote serveur (endroid/qr-code),
 * aucune bibliotheque JavaScript n'est requise. L'image est demandee a la
 * demande par le navigateur, ce qui evite d'embarquer des data URI dans le HTML.
 */
final class BilletQrCodeController extends AbstractController
{
    private const TAILLES_AUTORISEES = [100, 300, 320];
    private const TAILLE_PAR_DEFAUT = 300;

    public function __construct(
        private GenerateurImageQrCode $generateur
    ) {
    }

    #[Route(
        '/billet/{id}/qrcode.png',
        name: 'billet.qrcode',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Billet $billet, Request $request): Response
    {
        if (!$this->peutConsulter($billet)) {
            throw $this->createAccessDeniedException('Acces non autorise a ce billet.');
        }

        $taille = $request->query->getInt('taille', self::TAILLE_PAR_DEFAUT);
        if (!\in_array($taille, self::TAILLES_AUTORISEES, true)) {
            $taille = self::TAILLE_PAR_DEFAUT;
        }

        $png = $this->generateur->enPng((string) $billet->getQrCode(), $taille);

        $response = new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qrcode-' . $billet->getId() . '.png"',
        ]);
        // Donnee nominative : jamais mise en cache par un intermediaire partage.
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Le client proprietaire, l'organisateur de l'evenement et un administrateur.
     */
    private function peutConsulter(Billet $billet): bool
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return false;
        }

        if ($utilisateur->isAdmin()) {
            return true;
        }

        if ($billet->getClient()?->getId() === $utilisateur->getId()) {
            return true;
        }

        return $billet->getEvenement()?->getOrganisateur()?->getId() === $utilisateur->getId();
    }
}
