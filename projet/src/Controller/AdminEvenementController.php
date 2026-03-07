<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Service\ErrorHandlingService;
use App\Service\Upload\ServiceUploadFichier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Création d'événements par l'admin (upload sécurisé via ServiceUploadFichier).
 */
final class AdminEvenementController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private ServiceUploadFichier $serviceUploadFichier,
        private ErrorHandlingService $errorHandling
    ) {
    }

    #[Route('/admin/evenements', name: 'admin.evenement.index')]
    public function index(): Response
    {
        return $this->render('admin_evenement/index.html.twig');
    }

    #[Route('/admin/evenements/creer', name: 'admin.evenement.create')]
    public function create(Request $request, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, ['allow_file_upload' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $fichier = $form->get('affichePrincipale')->getData();
                if ($fichier instanceof UploadedFile) {
                    try {
                        $evenement->setAffichePrincipale($this->serviceUploadFichier->uploaderImageEvenement($fichier));
                    } catch (FileException $e) {
                        $this->errorHandling->handleFileUploadError($e);
                    }
                }

                $autresAffichesFiles = $form->get('autresAffiches')->getData();
                $autresAffichesUrls = [];
                if ($autresAffichesFiles) {
                    foreach ($autresAffichesFiles as $file) {
                        if ($file instanceof UploadedFile) {
                            try {
                                $autresAffichesUrls[] = $this->serviceUploadFichier->uploaderImageEvenement($file);
                            } catch (FileException $e) {
                                continue;
                            }
                        }
                    }
                }
                $evenement->setAutresAffiches($autresAffichesUrls);

                $evenement->setSlug($this->evenementRepository->generateUniqueSlug((string) $slugger->slug($evenement->getNom())));
                $this->evenementRepository->save($evenement, true);

                $this->errorHandling->addSuccessFlash('Événement créé avec succès !');
                return $this->redirectToRoute('admin.evenement.index');

            } catch (FileException $e) {
                $this->errorHandling->handleFileUploadError($e);
                $this->errorHandling->logError($e, ['action' => 'admin_create_event']);
            } catch (\Throwable $e) {
                $this->errorHandling->handleDatabaseError($e);
                $this->errorHandling->logError($e, ['action' => 'admin_create_event']);
            }
        } elseif ($form->isSubmitted()) {
            $this->errorHandling->handleFormErrors($form);
        }

        return $this->render('admin_evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
