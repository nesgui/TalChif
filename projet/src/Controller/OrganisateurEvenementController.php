<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function create(Request $request): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $evenement->setOrganisateur($user);
            $evenement->setSlug($this->generateSlug($evenement->getNom()));
            $evenement->setIsActive(true);
            $evenement->setIsValide(true);
            $evenement->setPlacesVendues(0);

            $this->evenementRepository->save($evenement, true);

            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('organisateur.evenement.index');
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

        $form = $this->createForm(EvenementType::class, $evenement);
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
}
