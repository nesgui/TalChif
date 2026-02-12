<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminUserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    private const USERS_PER_PAGE = 100;

    #[Route('/admin/utilisateurs', name: 'admin.user.index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('q');
        $limit = self::USERS_PER_PAGE;
        $users = $this->userRepository->findPaginated($page, $limit, $search);
        $total = $this->userRepository->countTotal($search);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin_user/index.html.twig', [
            'users' => $users,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'limit' => $limit,
            'search' => $search,
        ]);
    }

    #[Route('/admin/utilisateurs/creer', name: 'admin.user.create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'include_password' => true,
            'include_role' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que les mots de passe correspondent
            $password = $form->get('password')->getData();
            $passwordConfirm = $form->get('password_confirm')->getData();
            
            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('admin_user/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Hasher le mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Définir le rôle
            $user->setRole($form->get('role')->getData());
            
            // Synchroniser les rôles pour Symfony Security
            $user->setRoles([$user->getRole()]);

            // Définir l'utilisateur comme vérifié
            $user->setIsVerified(true);

            // Sauvegarder l'utilisateur
            $this->userRepository->save($user, true);

            $this->addFlash('success', 'Utilisateur créé avec succès !');
            return $this->redirectToRoute('admin.user.index');
        }

        return $this->render('admin_user/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/editer', name: 'admin.user.edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(User $user, Request $request): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'include_password' => false,
            'include_role' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le rôle
            $user->setRole($form->get('role')->getData());
            
            // Synchroniser les rôles pour Symfony Security
            $user->setRoles([$user->getRole()]);

            $this->userRepository->save($user, true);

            $this->addFlash('success', 'Utilisateur modifié avec succès !');
            return $this->redirectToRoute('admin.user.index');
        }

        return $this->render('admin_user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/supprimer', name: 'admin.user.delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user, Request $request): Response
    {
        // Empêcher la suppression de soi-même
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin.user.index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->userRepository->remove($user, true);

            $this->addFlash('success', 'Utilisateur supprimé avec succès !');
        }

        return $this->redirectToRoute('admin.user.index');
    }
}
