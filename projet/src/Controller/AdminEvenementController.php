<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Service\Tenant\ServiceOrganisation;
use App\Service\Notification\MessagesFlash;
use App\Service\Form\ErreursFormulaire;
use App\Service\Erreur\RapporteurErreurs;
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
        private MessagesFlash $messagesFlash,
        private ErreursFormulaire $erreursFormulaire,
        private ServiceOrganisation $serviceOrganisation,
        private RapporteurErreurs $rapporteurErreurs
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
            'allow_file_upload' => true,
            'include_organisation' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $fichier = $form->get('affichePrincipale')->getData();
                if ($fichier instanceof UploadedFile) {
                    try {
                        $evenement->setAffichePrincipale($this->serviceUploadFichier->uploaderImageEvenement($fichier));
                    } catch (FileException $e) {
                        $this->rapporteurErreurs->signalerUpload($e);
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

                // organisateur_id est NOT NULL : l'admin est enregistre comme
                // auteur, l'organisation proprietaire venant du formulaire.
                /** @var User $admin */
                $admin = $this->getUser();
                $evenement->setOrganisateur($admin);
                if ($evenement->getOrganisation() === null) {
                    $evenement->setOrganisation($this->serviceOrganisation->assurerPour($admin));
                }

                $evenement->setSlug($this->evenementRepository->generateUniqueSlug((string) $slugger->slug($evenement->getNom())));
                $this->evenementRepository->save($evenement, true);

                $this->messagesFlash->succes('Événement créé avec succès !');
                return $this->redirectToRoute('admin.evenement.index');

            } catch (FileException $e) {
                $this->rapporteurErreurs->signalerUpload($e, ['action' => 'admin_create_event']);
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'admin_create_event']);
            }
        } elseif ($form->isSubmitted()) {
            $this->erreursFormulaire->publierEnFlash($form);
        }

        return $this->render('admin_evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
