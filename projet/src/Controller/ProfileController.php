<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileInfoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'profile.index')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('auth.login');
        }

        if (!$user->isClient() && !$user->isOrganisateur()) {
            throw $this->createAccessDeniedException('Accès réservé aux profils client et organisateur.');
        }

        $infoForm = $this->createForm(ProfileInfoType::class, $user, [
            'action' => $this->generateUrl('profile.index'),
        ]);
        $passwordForm = $this->createForm(ChangePasswordType::class, null, [
            'action' => $this->generateUrl('profile.index'),
        ]);

        $infoForm->handleRequest($request);
        $passwordForm->handleRequest($request);

        if ($infoForm->isSubmitted() && $infoForm->isValid()) {
            if ($user->isCheckoutAccount() && trim((string) $user->getNom()) !== '' && trim((string) $user->getTelephone()) !== '') {
                $this->addFlash('info', 'Ajoutez maintenant un mot de passe pour sécuriser votre espace client.');
            }
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('profile.index');
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = (string) $passwordForm->get('current_password')->getData();
            $newPassword = (string) $passwordForm->get('new_password')->getData();
            $newPasswordConfirm = (string) $passwordForm->get('new_password_confirm')->getData();

            if (!$user->isCheckoutAccount() && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('profile.index');
            }

            if ($newPassword !== $newPasswordConfirm) {
                $this->addFlash('error', 'La confirmation du mot de passe ne correspond pas.');
                return $this->redirectToRoute('profile.index');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            if (trim((string) $user->getNom()) !== '' && trim((string) $user->getTelephone()) !== '') {
                $user->setCheckoutAccount(false);
            }
            $entityManager->flush();

            $this->addFlash('success', $user->isProfileComplete() ? 'Profil sécurisé. Vous pouvez maintenant consulter vos billets dans votre espace client.' : 'Mot de passe mis à jour.');
            return $this->redirectToRoute('profile.index');
        }

        return $this->render('profile/index.html.twig', [
            'infoForm' => $infoForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'profileIncomplete' => !$user->isProfileComplete(),
        ]);
    }
}

