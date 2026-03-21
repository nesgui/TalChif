<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ErrorHandlingService;
// TODO: Décommenter quand le mailer sera configuré
// use App\Service\Notification\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ErrorHandlingService $errorHandling
    ) {
    }

    #[Route('/connexion', name: 'auth.login')]
    public function login(Request $request): Response
    {
        // Si déjà connecté, rediriger vers le portefeuille
        if ($this->getUser()) {
            return $this->redirectToRoute('portefeuille.index');
        }

        // Gérer les erreurs de connexion avec le service d'erreurs
        $error = null;
        if ($request->getSession()->has('_security.last_error')) {
            $error = $request->getSession()->get('_security.last_error');
            $request->getSession()->remove('_security.last_error');
            
            // Utiliser le service pour gérer l'erreur de sécurité
            if ($error instanceof \Throwable) {
                $this->errorHandling->handleSecurityError($error);
            }
        }

        // Dernier email saisi pour pré-remplir le formulaire
        $lastUsername = $request->getSession()->get('_security.last_username');

        return $this->render('auth/login.html.twig', [
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
                $this->errorHandling->addErrorFlash('Les mots de passe ne correspondent pas');
                return $this->render('auth/register.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            try {
                // Hacher le mot de passe
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, $password)
                );
                // Règle métier : inscription depuis la page de connexion (sans auth) = client
                $user->setRole('CLIENT');
                $user->setIsVerified(false); // Pas encore vérifié
                
                // TODO: Activer l'envoi d'email quand le mailer sera configuré
                // Générer un token de vérification
                // $verificationToken = bin2hex(random_bytes(32));
                // $user->setVerificationToken($verificationToken);
                
                // Envoyer l'email de vérification
                // $this->emailVerification->envoyerEmailVerification($user, $verificationToken);

                // Sauvegarder en base
                $this->userRepository->save($user, true);

                $this->errorHandling->addSuccessFlash('Inscription réussie. Vous pouvez maintenant vous connecter.');

                // Rediriger vers la connexion
                return $this->redirectToRoute('auth.login');
                
            } catch (\Throwable $e) {
                $this->errorHandling->handleDatabaseError($e);
                $this->errorHandling->logError($e, ['action' => 'register']);
            }
        } elseif ($form->isSubmitted()) {
            // Utiliser le service pour gérer les erreurs de formulaire
            $this->errorHandling->handleFormErrors($form);
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // TODO: Décommenter cette route quand le mailer sera configuré
    /*
    #[Route('/verifier-email', name: 'auth.verify_email')]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');
        $email = $request->query->get('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !hash_equals($user->getVerificationToken() ?? '', (string) $token)) {
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('auth.login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->userRepository->save($user, true);

        $this->addFlash('success', 'Votre email est vérifié. Vous pouvez vous connecter !');
        return $this->redirectToRoute('auth.login');
    }
    */

    #[Route('/logout', name: 'auth.logout')]
    public function logout(): Response
    {
        // Cette méthode ne sera jamais exécutée car Symfony gère le logout
        throw new \Exception('This should never be reached!');
    }
}
