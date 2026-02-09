<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AdminEvenementController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
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
        $form = $this->createForm(EvenementType::class, $evenement, [
            'allow_file_upload' => true
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de l'affiche principale
            $affichePrincipaleFile = $form->get('affichePrincipale')->getData();
            if ($affichePrincipaleFile instanceof UploadedFile) {
                $newFilename = uniqid() . '.' . $affichePrincipaleFile->guessExtension();
                try {
                    $affichePrincipaleFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/evenements',
                        $newFilename
                    );
                    $evenement->setAffichePrincipale('/images/evenements/' . $newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur d'upload
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'affiche principale.');
                }
            }

            // Gérer l'upload des autres affiches
            $autresAffichesFiles = $form->get('autresAffiches')->getData();
            $autresAffichesUrls = [];
            if ($autresAffichesFiles) {
                foreach ($autresAffichesFiles as $file) {
                    if ($file instanceof UploadedFile) {
                        $newFilename = uniqid() . '.' . $file->guessExtension();
                        try {
                            $file->move(
                                $this->getParameter('kernel.project_dir') . '/public/images/evenements',
                                $newFilename
                            );
                            $autresAffichesUrls[] = '/images/evenements/' . $newFilename;
                        } catch (FileException $e) {
                            // Continuer même si une image échoue
                            continue;
                        }
                    }
                }
            }
            $evenement->setAutresAffiches(implode(',', $autresAffichesUrls));

            // Gérer l'upload de l'image billet
            $imageBilletFile = $form->get('imageBillet')->getData();
            if ($imageBilletFile instanceof UploadedFile) {
                $newFilename = uniqid() . '_ticket.' . $imageBilletFile->guessExtension();
                try {
                    $imageBilletFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/billets',
                        $newFilename
                    );
                    $evenement->setImageBillet('/images/billets/' . $newFilename);
                } catch (FileException $e) {
                    // Gérer l'erreur d'upload
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image billet.');
                }
            }

            // Générer le slug
            $evenement->setSlug($slugger->slug($evenement->getNom()));

            // Sauvegarder l'événement
            $this->evenementRepository->save($evenement, true);

            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('admin.evenement.index');
        }

        return $this->render('admin_evenement/create.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
