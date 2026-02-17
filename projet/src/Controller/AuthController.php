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

final class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/connexion', name: 'auth.login')]
    public function login(Request $request): Response
    {
        // Si déjà connecté, rediriger vers le portefeuille
        if ($this->getUser()) {
            return $this->redirectToRoute('portefeuille.index');
        }

        // Gérer les erreurs de connexion
        $error = null;
        if ($request->getSession()->has('_security.last_error')) {
            $error = $request->getSession()->get('_security.last_error');
            $request->getSession()->remove('_security.last_error');
        }

        // Dernier email saisi pour pré-remplir le formulaire
        $lastUsername = $request->getSession()->get('_security.last_username');

        return $this->render('auth/login_new.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/inscription', name: 'auth.register')]
    public function register(Request $request): Response
    {
        // Si déjà connecté, rediriger vers le portefeuille
        if ($this->getUser()) {
            return $this->redirectToRoute('portefeuille.index');
        }

        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'include_password' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les mots de passe du formulaire
            $password = $form->get('password')->getData();
            $passwordConfirm = $form->get('password_confirm')->getData();

            // Vérifier que les mots de passe correspondent
            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas');
                return $this->render('auth/register_new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Hacher le mot de passe
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $password)
            );
            // Règle métier : inscription depuis la page de connexion (sans auth) = client
            $user->setRole('CLIENT');
            $user->setIsVerified(false); // TODO: Implémenter l'email de vérification

            // Sauvegarder en base
            $this->userRepository->save($user, true);

            $this->addFlash('success', 'Inscription réussie. Vous pouvez maintenant vous connecter.');

            // Rediriger vers la connexion
            return $this->redirectToRoute('auth.login');
        }

        return $this->render('auth/register_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/logout', name: 'auth.logout')]
    public function logout(): Response
    {
        // Cette méthode ne sera jamais exécutée car Symfony gère le logout
        throw new \Exception('This should never be reached!');
    }
}
