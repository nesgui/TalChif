<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Commande;
use App\Domain\Repository\BilletRepositoryInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BilletEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private BilletRepositoryInterface $billetRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function envoyerConfirmationAchat(Commande $commande): void
    {
        $client = $commande->getClient();
        $emailClient = $client?->getEmail();

        if (!$client || !$emailClient) {
            return;
        }

        $reference = $commande->getReference();
        if (!$reference) {
            return;
        }

        $billets = $this->billetRepository->findByTransactionId($reference);

        $billetsCards = [];
        foreach ($billets as $billet) {
            $qrCode = $billet->getQrCode();
            if (!$qrCode) {
                continue;
            }

            $billetsCards[] = [
                'billet' => $billet,
                'billetUrl' => $this->urlGenerator->generate(
                    'achat.billet',
                    ['qrCode' => $qrCode],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ];
        }

        $email = (new TemplatedEmail())
            ->from('no-reply@talchif.local')
            ->to($emailClient)
            ->subject('Confirmation de votre achat - ' . $commande->getReference())
            ->htmlTemplate('emails/confirmation_achat.html.twig')
            ->context([
                'commande' => $commande,
                'client' => $client,
                'billetsCards' => $billetsCards,
            ]);

        $this->mailer->send($email);
    }
}

