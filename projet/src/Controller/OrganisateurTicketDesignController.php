<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\TicketDesign;
use App\Entity\User;
use App\Repository\TicketDesignRepository;
use App\Service\Upload\ServiceUploadFichier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OrganisateurTicketDesignController extends AbstractController
{
    public function __construct(
        private TicketDesignRepository $ticketDesignRepository,
        private EntityManagerInterface $entityManager,
        private ServiceUploadFichier $serviceUploadFichier,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    #[Route('/organisateur/evenements/{id}/billet-design', name: 'organisateur.evenement.billet_design')]
    #[Route('/organisateur/evenement/{id}/billet-design', name: 'organisateur.evenement.billet_design_legacy')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function billetDesign(Evenement $evenement, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet événement.');
        }

        $typeBillet = $request->request->get('typeBillet', $request->query->get('typeBillet', TicketDesign::TYPE_SIMPLE));
        if (!\in_array($typeBillet, [TicketDesign::TYPE_SIMPLE, TicketDesign::TYPE_VIP], true)) {
            $typeBillet = TicketDesign::TYPE_SIMPLE;
        }

        $ticketDesign = $this->ticketDesignRepository->findOneForEvenementAndType($evenement, $typeBillet);
        if (!$ticketDesign) {
            $ticketDesign = (new TicketDesign())
                ->setEvenement($evenement)
                ->setTypeBillet($typeBillet);
            $this->entityManager->persist($ticketDesign);
        }

        if ($request->isMethod('POST')) {
            $mode = (string) $request->request->get('mode', 'manual');
            $mode = $mode === 'auto' ? 'auto' : 'manual';

            $markerColor = (string) $request->request->get('markerColor', $ticketDesign->getMarkerColor() ?? '#0d1321');
            $ticketDesign->setMarkerColor($markerColor);

            $fichier = $request->files->get('designPng');
            if ($fichier instanceof UploadedFile) {
                try {
                    $publicPath = $this->serviceUploadFichier->uploaderTicketDesignPng($fichier);
                    $ticketDesign->setDesignPath($publicPath);

                    $absolutePath = $this->projectDir . '/public' . $publicPath;
                    $size = @getimagesize($absolutePath);
                    if (!\is_array($size)) {
                        $this->addFlash('error', 'Impossible de lire les dimensions du PNG.');
                        return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
                    }
                    $ticketDesign->setDesignWidth((int) $size[0]);
                    $ticketDesign->setDesignHeight((int) $size[1]);
                } catch (FileException $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
                }
            }

            if (!$ticketDesign->getDesignPath()) {
                $this->addFlash('error', 'Veuillez uploader un design PNG.');
                return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
            }

            if ($mode === 'manual') {
                $qrX = $request->request->getInt('qrX', -1);
                $qrY = $request->request->getInt('qrY', -1);
                $qrW = $request->request->getInt('qrW', -1);
                $qrH = $request->request->getInt('qrH', -1);

                if ($qrX < 0 || $qrY < 0 || $qrW <= 0 || $qrH <= 0) {
                    $this->addFlash('error', 'Veuillez sélectionner la zone QR (rectangle).');
                    return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
                }

                $ticketDesign
                    ->setQrX($qrX)
                    ->setQrY($qrY)
                    ->setQrW($qrW)
                    ->setQrH($qrH);
            } else {
                try {
                    $zone = $this->detectMarkerZone($ticketDesign);
                    $ticketDesign
                        ->setQrX($zone['x'])
                        ->setQrY($zone['y'])
                        ->setQrW($zone['w'])
                        ->setQrH($zone['h']);
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Design billet enregistré.');

            return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
        }

        return $this->render('organisateur_evenement/billet_design.html.twig', [
            'evenement' => $evenement,
            'ticketDesign' => $ticketDesign,
            'typeBillet' => $typeBillet,
        ]);
    }

    /**
     * @return array{x:int,y:int,w:int,h:int}
     */
    private function detectMarkerZone(TicketDesign $ticketDesign): array
    {
        $designPath = $ticketDesign->getDesignPath();
        if (!$designPath) {
            throw new \RuntimeException('Aucun design chargé.');
        }

        $absolutePath = $this->projectDir . '/public' . $designPath;
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException('Design introuvable sur le serveur.');
        }

        $img = @imagecreatefrompng($absolutePath);
        if (!$img) {
            throw new \RuntimeException('Impossible de lire le PNG (GD).');
        }

        $markerHex = (string) ($ticketDesign->getMarkerColor() ?? '#0d1321');
        $markerHex = ltrim(strtolower($markerHex), '#');
        if (strlen($markerHex) !== 6 || !ctype_xdigit($markerHex)) {
            imagedestroy($img);
            throw new \RuntimeException('Couleur marqueur invalide.');
        }

        $rTarget = hexdec(substr($markerHex, 0, 2));
        $gTarget = hexdec(substr($markerHex, 2, 2));
        $bTarget = hexdec(substr($markerHex, 4, 2));

        $width = imagesx($img);
        $height = imagesy($img);

        $minX = null;
        $minY = null;
        $maxX = null;
        $maxY = null;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($r === $rTarget && $g === $gTarget && $b === $bTarget) {
                    $minX = $minX === null ? $x : min($minX, $x);
                    $minY = $minY === null ? $y : min($minY, $y);
                    $maxX = $maxX === null ? $x : max($maxX, $x);
                    $maxY = $maxY === null ? $y : max($maxY, $y);
                }
            }
        }

        imagedestroy($img);

        if ($minX === null || $minY === null || $maxX === null || $maxY === null) {
            throw new \RuntimeException('Marqueur introuvable dans le PNG. Utilisez la sélection manuelle ou ajoutez un carré de couleur #0d1321.');
        }

        $w = ($maxX - $minX) + 1;
        $h = ($maxY - $minY) + 1;
        if ($w < 5 || $h < 5) {
            throw new \RuntimeException('Marqueur détecté mais trop petit.');
        }

        return [
            'x' => (int) $minX,
            'y' => (int) $minY,
            'w' => (int) $w,
            'h' => (int) $h,
        ];
    }
}
