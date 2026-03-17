<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PaymentReferenceNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class PaymentReferenceNotificationHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%app.notifications.whatsapp.enabled%')]
        private bool $whatsAppEnabled,
        #[Autowire('%app.notifications.whatsapp.token%')]
        private ?string $whatsAppToken,
        #[Autowire('%app.notifications.whatsapp.phone_number_id%')]
        private ?string $whatsAppPhoneNumberId
    ) {
    }

    public function __invoke(PaymentReferenceNotificationMessage $message): void
    {
        foreach ($message->destinataires as $destinataire) {
            $email = (string) ($destinataire['email'] ?? '');
            $telephone = $this->normalizePhone((string) ($destinataire['telephone'] ?? ''));
            $evenement = (string) ($destinataire['evenement'] ?? 'Evenement');

            if ($email !== '') {
                $this->sendEmail($message, $email, $evenement);
            }

            if ($telephone !== null) {
                $this->sendWhatsApp($message, $telephone, $evenement);
            }
        }
    }

    private function sendEmail(PaymentReferenceNotificationMessage $message, string $recipient, string $evenement): void
    {
        try {
            $email = (new Email())
                ->from('no-reply@talchif.local')
                ->to($recipient)
                ->subject('Verification paiement client - ' . $message->commandeReference)
                ->text(
                    "Verification paiement client\n\n" .
                    "Evenement: {$evenement}\n" .
                    "Commande: {$message->commandeReference}\n" .
                    "Montant: " . number_format($message->montantTotal, 0, '.', ' ') . " FCFA\n" .
                    "Client: {$message->clientNom} ({$message->clientEmail})\n" .
                    "Telephone client: {$message->clientTelephone}\n" .
                    "Operateur: {$message->operateur}\n" .
                    "Reference transaction client: {$message->referenceTransactionClient}\n\n" .
                    "Merci de verifier puis valider la commande dans l'admin."
                );
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Echec envoi email verification paiement', [
                'commande' => $message->commandeReference,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(PaymentReferenceNotificationMessage $message, string $telephone, string $evenement): void
    {
        if (
            !$this->whatsAppEnabled
            || empty($this->whatsAppToken)
            || empty($this->whatsAppPhoneNumberId)
        ) {
            return;
        }

        $url = sprintf('https://graph.facebook.com/v18.0/%s/messages', $this->whatsAppPhoneNumberId);
        $bodyText =
            "Verification paiement client\n" .
            "Evt: {$evenement}\n" .
            "Ref cmd: {$message->commandeReference}\n" .
            "Montant: " . number_format($message->montantTotal, 0, '.', ' ') . " FCFA\n" .
            "Operateur: {$message->operateur}\n" .
            "Ref transaction: {$message->referenceTransactionClient}";

        try {
            $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->whatsAppToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $telephone,
                    'type' => 'text',
                    'text' => ['body' => $bodyText],
                ],
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('Echec envoi WhatsApp verification paiement', [
                'commande' => $message->commandeReference,
                'to' => $telephone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $telephone): ?string
    {
        $digits = preg_replace('/\D+/', '', $telephone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '235') || str_starts_with($digits, '237') || str_starts_with($digits, '225')) {
            return $digits;
        }

        return null;
    }
}

