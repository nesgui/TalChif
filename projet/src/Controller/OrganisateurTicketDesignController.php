<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\TicketDesign;
use App\Entity\User;
use App\Repository\TicketDesignRepository;
use App\Service\Ticket\TicketRenderService;
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
        private TicketRenderService $ticketRenderService,
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

            try {
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'no such column')) {
                    $this->addFlash('error', 'Le schéma de base de données n\'est pas à jour. Exécutez : php bin/console doctrine:migrations:migrate');
                } else {
                    $this->addFlash('error', 'Impossible d\'enregistrer le design. ' . $msg);
                }
                return $this->redirectToRoute('organisateur.evenement.billet_design', ['id' => $evenement->getId(), 'typeBillet' => $typeBillet]);
            }

            $this->addFlash('success', 'Design billet enregistré. L\'aperçu est visible ci-dessous.');

            return $this->redirectToRoute('organisateur.evenement.show', ['id' => $evenement->getId()]);
        }

        return $this->render('organisateur_evenement/billet_design.html.twig', [
            'evenement' => $evenement,
            'ticketDesign' => $ticketDesign,
            'typeBillet' => $typeBillet,
        ]);
    }

    #[Route('/organisateur/evenement/{id}/apercu-billets', name: 'organisateur.evenement.apercu_billets')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function apercuBillets(Evenement $evenement): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir cet événement.');
        }

        $designSimple = $this->ticketDesignRepository->findOneForEvenementAndType($evenement, TicketDesign::TYPE_SIMPLE);
        $designVip = $this->ticketDesignRepository->findOneForEvenementAndType($evenement, TicketDesign::TYPE_VIP);

        $gdDisponible = \function_exists('imagecreatetruecolor');

        return $this->render('organisateur_evenement/apercu_billets.html.twig', [
            'evenement' => $evenement,
            'designSimple' => $designSimple,
            'designVip' => $designVip,
            'gdDisponible' => $gdDisponible,
        ]);
    }

    #[Route('/organisateur/evenements/{id}/billet-design/preview', name: 'organisateur.evenement.billet_design_preview', methods: ['GET'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function preview(Evenement $evenement, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet événement.');
        }

        $typeBillet = (string) $request->query->get('typeBillet', TicketDesign::TYPE_SIMPLE);
        if (!\in_array($typeBillet, [TicketDesign::TYPE_SIMPLE, TicketDesign::TYPE_VIP], true)) {
            $typeBillet = TicketDesign::TYPE_SIMPLE;
        }

        $ticketDesign = $this->ticketDesignRepository->findOneForEvenementAndType($evenement, $typeBillet);
        if (!$ticketDesign || !$ticketDesign->getDesignPath()) {
            return new Response('', 404);
        }

        $png = $this->ticketRenderService->renderPreviewPngForDesign($ticketDesign, 'PREVIEW-' . $typeBillet);
        if ($png === null) {
            return new Response('', 500);
        }

        return new Response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}
