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

    #[Route('/organisateur/evenements', name: 'organisateur.evenement.index')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $evenements = $this->evenementRepository->findByOrganisateur($user);

        return $this->render('organisateur_evenement/index.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/organisateur/evenements/creer', name: 'organisateur.evenement.create')]
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
            $evenement->setSlug($slugger->slug($evenement->getNom()));
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

    #[Route('/organisateur/evenements/{id}/editer', name: 'organisateur.evenement.edit')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function edit(Evenement $evenement, Request $request): Response
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
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setSlug($this->generateSlug($evenement->getNom()));
            $this->evenementRepository->save($evenement, true);

            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('organisateur.evenement.index');
        }

        return $this->render('organisateur_evenement/edit.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }

    #[Route('/organisateur/evenements/{id}/supprimer', name: 'organisateur.evenement.delete', methods: ['POST'])]
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

    #[Route('/organisateur/evenements/{id}', name: 'organisateur.evenement.show', requirements: ['id' => '\\d+'])]
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

    private function generateSlug(string $nom): string
    {
        // Convertir en minuscules et remplacer les caractères spéciaux
        $slug = strtolower($nom);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ajouter un suffixe si le slug existe déjà
        $originalSlug = $slug;
        $counter = 1;
        while ($this->evenementRepository->findOneBy(['slug' => $slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    #[Route('/organisateur/evenements/{id}/toggle-status/{action}', name: 'organisateur.evenement.toggle_status', methods: ['POST'])]
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
