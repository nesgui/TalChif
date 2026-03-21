<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service d'envoi d'email de vérification
 * 
 * TODO: Activer ce service quand le mailer sera configuré (MAILER_DSN dans .env)
 * Pour l'instant, le service est désactivé car MAILER_DSN=null://null
 */
final class EmailVerificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $expediteur = 'noreply@talchif.com'
    ) {}

    public function envoyerEmailVerification(User $user, string $token): void
    {
        // TODO: Décommenter cette méthode quand le mailer sera configuré
        /*
        if ($_ENV['MAILER_DSN'] === 'null://null') {
            // Mailer non configuré, ne pas envoyer d'email
            return;
        }

        $lienVerification = $this->urlGenerator->generate(
            'auth.verify_email',
            ['token' => $token, 'email' => $user->getEmail()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->expediteur, 'TalChif'))
            ->to(new Address($user->getEmail(), $user->getNom()))
            ->subject('Vérifiez votre adresse email — TalChif')
            ->htmlTemplate('emails/verification_email.html.twig')
            ->context([
                'user'              => $user,
                'lienVerification'  => $lienVerification,
                'expirationHeures'  => 24,
            ]);

        $this->mailer->send($email);
        */
        
        // Pour l'instant, on ne fait rien (mailer non configuré)
        // Quand le mailer sera configuré, décommenter le code ci-dessus
    }
}
