<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\BilletRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organisateurs')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOrganisateurController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private BilletRepository $billetRepository,
    ) {}

    #[Route('', name: 'admin.organisateur.index')]
    public function index(Request $request): Response
    {
        $page   = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('q');

        $organisateurs = $this->userRepository->findPaginated($page, 50, $search);
        // Filtrer uniquement les organisateurs
        $organisateurs = array_filter($organisateurs, fn(User $u) => $u->isOrganisateur());

        $total      = $this->userRepository->countByRole('ORGANISATEUR');
        $totalPages = max(1, (int) ceil($total / 50));

        return $this->render('admin_organisateur/index.html.twig', [
            'organisateurs' => $organisateurs,
            'total'         => $total,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'search'        => $search,
        ]);
    }

    #[Route('/{id}', name: 'admin.organisateur.show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(User $user): Response
    {
        if (!$user->isOrganisateur()) {
            throw $this->createAccessDeniedException('Cet utilisateur n\'est pas un organisateur.');
        }

        return $this->render('admin_organisateur/show.html.twig', [
            'organisateur' => $user,
        ]);
    }

    #[Route('/{id}/editer', name: 'admin.organisateur.edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(User $user, Request $request): Response
    {
        if (!$user->isOrganisateur()) {
            throw $this->createAccessDeniedException('Cet utilisateur n\'est pas un organisateur.');
        }

        $form = $this->createForm(UserType::class, $user, [
            'include_password' => false,
            'include_role' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userRepository->save($user, true);
            $this->addFlash('success', "Organisateur mis à jour avec succès.");
            return $this->redirectToRoute('admin.organisateur.index');
        }

        return $this->render('admin_organisateur/edit.html.twig', [
            'form' => $form->createView(),
            'organisateur' => $user,
        ]);
    }

    #[Route('/{id}/toggle-actif', name: 'admin.organisateur.toggle_actif', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActif(User $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_organisateur_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin.organisateur.index');
        }

        if (!$user->isOrganisateur()) {
            throw $this->createAccessDeniedException('Cet utilisateur n\'est pas un organisateur.');
        }

        $user->setActif(!$user->isActif());
        $this->userRepository->save($user, true);

        $statut = $user->isActif() ? 'réactivé' : 'suspendu';
        $this->addFlash('success', "L'organisateur {$user->getNom()} a été {$statut}.");

        return $this->redirectToRoute('admin.organisateur.index');
    }
}
