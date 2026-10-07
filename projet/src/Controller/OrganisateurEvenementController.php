<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Service\Notification\MessagesFlash;
use App\Service\Form\ErreursFormulaire;
use App\Service\Erreur\RapporteurErreurs;
use App\Security\Voter\EvenementVoter;
use App\Service\Upload\ServiceUploadFichier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gestion des événements par l'organisateur (CRUD).
 * Les uploads sont délégués à ServiceUploadFichier (validation MIME, noms sécurisés).
 */
final class OrganisateurEvenementController extends AbstractController
{
    private const EVENEMENTS_PAR_PAGE = 100;

    public function __construct(
        private EvenementRepository $evenementRepository,
        private ServiceUploadFichier $serviceUploadFichier,
        private MessagesFlash $messagesFlash,
        private ErreursFormulaire $erreursFormulaire,
        private RapporteurErreurs $rapporteurErreurs
    ) {
    }

    #[Route('/organisateur/evenement', name: 'organisateur.evenement.index')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('q');
        $limit = self::EVENEMENTS_PAR_PAGE;
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
        $form = $this->createForm(EvenementType::class, $evenement, ['allow_file_upload' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var User $user */
                $user = $this->getUser();
                $evenement->setOrganisateur($user);
                $evenement->setSlug($this->evenementRepository->generateUniqueSlug((string) $slugger->slug($evenement->getNom())));
                $evenement->setPlacesVendues(0);

                $this->traiterUploadsCreation($form, $evenement);
                $this->evenementRepository->save($evenement, true);

                $this->messagesFlash->succes('Événement créé avec succès !');
                return $this->redirectToRoute('organisateur.evenement.index');
                
            } catch (FileException $e) {
                $this->rapporteurErreurs->signalerUpload($e, ['action' => 'create_event']);
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'create_event']);
            }
        } elseif ($form->isSubmitted()) {
            // Utiliser le service pour gérer les erreurs de formulaire
            $this->erreursFormulaire->publierEnFlash($form);
        }

        return $this->render('organisateur_evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/organisateur/evenement/{id}/editer', name: 'organisateur.evenement.edit')]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function edit(Evenement $evenement, Request $request, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted(EvenementVoter::MODIFIER, $evenement);

        $form = $this->createForm(EvenementType::class, $evenement, ['allow_file_upload' => true]);
        $nomAvant = $evenement->getNom();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($evenement->getNom() !== $nomAvant) {
                    $evenement->setSlug($this->evenementRepository->generateUniqueSlug(
                        (string) $slugger->slug($evenement->getNom()),
                        $evenement->getId()
                    ));
                }

                $this->traiterUploadsEdition($form, $evenement);
                $this->evenementRepository->save($evenement, true);

                $this->messagesFlash->succes('Événement modifié avec succès !');
                return $this->redirectToRoute('organisateur.evenement.index');

            } catch (FileException $e) {
                $this->rapporteurErreurs->signalerUpload($e, ['action' => 'edit_event', 'id' => $evenement->getId()]);
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'edit_event', 'id' => $evenement->getId()]);
            }
        } elseif ($form->isSubmitted()) {
            $this->erreursFormulaire->publierEnFlash($form);
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
        $this->denyAccessUnlessGranted(EvenementVoter::MODIFIER, $evenement);

        if (!$this->isCsrfTokenValid('delete' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->messagesFlash->erreur('Token de securite invalide. Veuillez reessayer.');

            return $this->redirectToRoute('organisateur.evenement.index');
        }

        $this->evenementRepository->remove($evenement, true);
        $this->messagesFlash->succes('Evenement supprime avec succes !');

        return $this->redirectToRoute('organisateur.evenement.index');
    }

    #[Route('/organisateur/evenement/{id}', name: 'organisateur.evenement.show', requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function show(Evenement $evenement): Response
    {
        $this->denyAccessUnlessGranted(EvenementVoter::CONSULTER, $evenement);

        return $this->render('organisateur_evenement/show.html.twig', ['evenement' => $evenement]);
    }

    #[Route('/organisateur/evenement/{id}/toggle-status/{action}', name: 'organisateur.evenement.toggle_status', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANISATEUR')]
    public function toggleStatus(Request $request, Evenement $evenement, string $action): Response
    {
        $this->denyAccessUnlessGranted(EvenementVoter::MODIFIER, $evenement);

        if (!$this->isCsrfTokenValid('status' . $evenement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        if ($action === 'activate') {
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $this->addFlash('success', "L'evenement a ete active et publie avec succes !");
        } elseif ($action === 'deactivate') {
            $evenement->setIsActive(false);
            $this->addFlash('warning', "L'evenement a ete desactive et n'est plus visible par les utilisateurs.");
        }

        $this->evenementRepository->save($evenement, true);

        return $this->redirectToRoute('organisateur.evenement.show', ['id' => $evenement->getId()]);
    }

    /**
     * Traite les champs fichier du formulaire en création.
     */
    private function traiterUploadsCreation($form, Evenement $evenement): void
    {
        if ($form->has('affichePrincipale')) {
            $fichier = $form->get('affichePrincipale')->getData();
            if ($fichier instanceof UploadedFile) {
                try {
                    $evenement->setAffichePrincipale($this->serviceUploadFichier->uploaderImageEvenement($fichier));
                } catch (FileException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        $urls = [];
        if ($form->has('autresAffiches')) {
            $autres = $form->get('autresAffiches')->getData();
            if (!empty($autres)) {
                foreach ($autres as $file) {
                    if ($file instanceof UploadedFile) {
                        try {
                            $urls[] = $this->serviceUploadFichier->uploaderImageEvenement($file);
                        } catch (FileException $e) {
                            continue;
                        }
                    }
                }
            }
        }
        $evenement->setAutresAffiches($urls);
    }

    /**
     * Traite les champs fichier du formulaire en édition (nouveaux fichiers uniquement).
     */
    private function traiterUploadsEdition($form, Evenement $evenement): void
    {
        if ($form->has('affichePrincipale')) {
            $fichier = $form->get('affichePrincipale')->getData();
            if ($fichier instanceof UploadedFile) {
                try {
                    $evenement->setAffichePrincipale($this->serviceUploadFichier->uploaderImageEvenement($fichier));
                } catch (FileException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        if ($form->has('autresAffiches')) {
            $autres = $form->get('autresAffiches')->getData();
            $urls = $evenement->getAutresAffiches() ?? [];
            if (!empty($autres)) {
                foreach ($autres as $file) {
                    if ($file instanceof UploadedFile) {
                        try {
                            $urls[] = $this->serviceUploadFichier->uploaderImageEvenement($file);
                        } catch (FileException $e) {
                            continue;
                        }
                    }
                }
                $evenement->setAutresAffiches($urls);
            }
        }
    }
}
