<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\String\Slugger\SluggerInterface;

final class OrganisateurEvenementController extends AbstractController
{
    public function __construct(
        private EvenementRepository $evenementRepository
    ) {
    }

    private const EVENTS_PER_PAGE = 100;

    #[Route('/organisateur/evenement', name: 'organisateur.evenement.index')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('q');
        $limit = self::EVENTS_PER_PAGE;
        $evenements = $this->evenementRepository->findPaginatedByOrganisateur($user, $page, $limit, $search);
        $total = $this->evenementRepository->countByOrganisateur($user, $search);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('organisateur_evenement/index.html.twig', [
            'evenements' => $evenements,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'limit' => $limit,
            'search' => $search,
        ]);
    }

    #[Route('/organisateur/evenement/creer', name: 'organisateur.evenement.create')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function create(Request $request, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, [
            'allow_file_upload' => true
        ]);

        $form->handleRequest($request);

        // Debug: Log form submission status
        if ($request->isMethod('POST')) {
            error_log('Form submitted - isSubmitted: ' . ($form->isSubmitted() ? 'true' : 'false'));
            error_log('Form submitted - isValid: ' . ($form->isValid() ? 'true' : 'false'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $evenement->setOrganisateur($user);
            $baseSlug = (string) $slugger->slug($evenement->getNom());
            $evenement->setSlug($this->evenementRepository->generateUniqueSlug($baseSlug));
            $evenement->setPlacesVendues(0);

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
            if (!empty($autresAffichesFiles)) {
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
            $evenement->setAutresAffiches($autresAffichesUrls);

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

            // Sauvegarder l'événement
            $this->evenementRepository->save($evenement, true);

            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('organisateur.evenement.index');
        } else {
            // Ajouter des logs pour voir pourquoi le formulaire n'est pas valide
            if ($form->isSubmitted()) {
                $errors = $form->getErrors(true);
                error_log('Form validation errors count: ' . count($errors));
                foreach ($errors as $error) {
                    error_log('Form error: ' . $error->getMessage());
                    error_log('Form error field: ' . $error->getOrigin()->getName());
                    $this->addFlash('error', $error->getMessage());
                }
                
                // Log des données soumises pour débogage
                $data = $form->getData();
                error_log('Submitted data - nom: ' . $data->getNom());
                error_log('Submitted data - prixSimple: ' . $data->getPrixSimple());
                error_log('Submitted data - prixVip: ' . $data->getPrixVip());
            }
        }

        return $this->render('organisateur_evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/organisateur/evenement/{id}/editer', name: 'organisateur.evenement.edit')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function edit(Evenement $evenement, Request $request, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Vérifier que l'événement appartient à l'organisateur
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet événement.');
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'allow_file_upload' => true
        ]);
        $nomAvant = $evenement->getNom();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            error_log('DEBUG: Form is submitted and valid - starting event update');

            // Ne mettre à jour le slug que si le nom a vraiment changé (évite de casser le lien quand on modifie seulement une image)
            if ($evenement->getNom() !== $nomAvant) {
                $baseSlug = (string) $slugger->slug($evenement->getNom());
                $newSlug = $this->evenementRepository->generateUniqueSlug($baseSlug, $evenement->getId());
                $evenement->setSlug($newSlug);
                error_log('DEBUG: Slug updated to ' . $newSlug . ' (nom modifié)');
            }

            // Gérer l'upload de l'affiche principale
            $affichePrincipaleFile = $form->get('affichePrincipale')->getData();
            if ($affichePrincipaleFile instanceof UploadedFile) {
                error_log('DEBUG: Processing affiche principale upload');
                $newFilename = uniqid() . '.' . $affichePrincipaleFile->guessExtension();
                try {
                    $affichePrincipaleFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/evenements',
                        $newFilename
                    );
                    $evenement->setAffichePrincipale('/images/evenements/' . $newFilename);
                    error_log('DEBUG: Affiche principale uploaded successfully');
                } catch (FileException $e) {
                    error_log('ERROR: Upload failed - ' . $e->getMessage());
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'affiche principale: ' . $e->getMessage());
                }
            }

            // Gérer l'upload des autres affiches
            $autresAffichesFiles = $form->get('autresAffiches')->getData();
            $autresAffichesUrls = $evenement->getAutresAffiches() ?? [];
            if (!empty($autresAffichesFiles)) {
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
                            continue;
                        }
                    }
                }
                $evenement->setAutresAffiches($autresAffichesUrls);
            }

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
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image billet.');
                }
            }

            error_log('DEBUG: About to save event to database');
            $this->evenementRepository->save($evenement, true);
            error_log('DEBUG: Event saved successfully');

            $this->addFlash('success', 'Événement modifié avec succès !');
            error_log('DEBUG: Redirecting to event index');
            return $this->redirectToRoute('organisateur.evenement.index');
        } else {
            // Ajouter des logs pour le débogage
            if ($form->isSubmitted()) {
                error_log('DEBUG: Form submitted but INVALID');
                $errors = $form->getErrors(true);
                error_log('DEBUG: Form validation errors count: ' . count($errors));
                foreach ($errors as $error) {
                    error_log('DEBUG: Form error: ' . $error->getMessage());
                    error_log('DEBUG: Form error field: ' . $error->getOrigin()->getName());
                    $this->addFlash('error', $error->getMessage());
                }
            } else {
                error_log('DEBUG: Form not submitted');
            }
        }

        return $this->render('organisateur_evenement/edit.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }

    #[Route('/organisateur/evenement/{id}/supprimer', name: 'organisateur.evenement.delete', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function delete(Evenement $evenement, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Vérifier que l'événement appartient à l'organisateur
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cet événement.');
        }

        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $this->evenementRepository->remove($evenement, true);
            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('organisateur.evenement.index');
    }

    #[Route('/organisateur/evenement/{id}', name: 'organisateur.evenement.show', requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function show(Evenement $evenement): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Vérifier que l'événement appartient à l'organisateur
        if ($evenement->getOrganisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir cet événement.');
        }

        return $this->render('organisateur_evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/organisateur/evenement/{id}/toggle-status/{action}', name: 'organisateur.evenement.toggle_status', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function toggleStatus(Request $request, Evenement $evenement, string $action, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Vérifier le token CSRF
        $token = new CsrfToken('status' . $evenement->getId(), $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        // Vérifier que l'utilisateur est bien l'organisateur de l'événement
        if ($evenement->getOrganisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas l\'organisateur de cet événement');
        }

        if ($action === 'activate') {
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $this->addFlash('success', 'L\'événement a été activé et publié avec succès !');
        } elseif ($action === 'deactivate') {
            $evenement->setIsActive(false);
            $this->addFlash('warning', 'L\'événement a été désactivé et n\'est plus visible par les utilisateurs.');
        }

        $this->evenementRepository->save($evenement, true);

        return $this->redirectToRoute('organisateur.evenement.show', ['id' => $evenement->getId()]);
    }
}
