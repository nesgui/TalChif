<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\Notification\MessagesFlash;
use App\Service\Form\ErreursFormulaire;
use App\Service\Erreur\RapporteurErreurs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminUserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private MessagesFlash $messagesFlash,
        private ErreursFormulaire $erreursFormulaire,
        private RapporteurErreurs $rapporteurErreurs
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
                $this->messagesFlash->erreur('Les mots de passe ne correspondent pas.');
                return $this->render('admin_user/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                // Hasher le mot de passe
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // Le role metier est la source de verite : User::getRoles() en derive.
                $user->setRole($form->get('role')->getData());

                // Définir l'utilisateur comme vérifié
                $user->setIsVerified(true);

                // Sauvegarder l'utilisateur
                $this->userRepository->save($user, true);

                $this->messagesFlash->succes('Utilisateur créé avec succès !');
                return $this->redirectToRoute('admin.user.index');
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'admin_create_user']);
            }
        } elseif ($form->isSubmitted()) {
            $this->erreursFormulaire->publierEnFlash($form);
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
            try {
                // Le role metier est la source de verite : User::getRoles() en derive.
                $user->setRole($form->get('role')->getData());

                $this->userRepository->save($user, true);

                $this->messagesFlash->succes('Utilisateur modifié avec succès !');
                return $this->redirectToRoute('admin.user.index');
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'admin_edit_user', 'user_id' => $user->getId()]);
            }
        } elseif ($form->isSubmitted()) {
            $this->erreursFormulaire->publierEnFlash($form);
        }

        return $this->render('admin_user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/toggle-actif', name: 'admin.user.toggle_actif', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleActif(User $user, Request $request): Response
    {
        // Empêcher la désactivation de soi-même
        if ($user === $this->getUser()) {
            $this->messagesFlash->erreur('Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('admin.user.index');
        }

        if ($this->isCsrfTokenValid('toggle_actif' . $user->getId(), $request->request->get('_token'))) {
            try {
                $user->setActif(!$user->isActif());
                $this->userRepository->save($user, true);

                $statut = $user->isActif() ? 'activé' : 'désactivé';
                $this->messagesFlash->succes("Le compte de {$user->getNom()} a été {$statut}.");
            } catch (\Throwable $e) {
                $this->rapporteurErreurs->signalerBaseDeDonnees($e, ['action' => 'toggle_actif', 'user_id' => $user->getId()]);
            }
        }

        return $this->redirectToRoute('admin.user.index');
    }
}
