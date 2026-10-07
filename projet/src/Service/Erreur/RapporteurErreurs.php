<?php

declare(strict_types=1);

namespace App\Service\Erreur;

use App\Service\Notification\MessagesUtilisateurInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\Exception\InvalidCsrfTokenException;

/**
 * Traduit une exception technique en message utilisateur, et la journalise.
 *
 * Les deux actions vont ensemble : un incident signale a l'utilisateur sans
 * trace exploitable est un incident perdu. Chaque methode fait donc les deux,
 * la ou les appelants devaient auparavant enchainer deux appels.
 */
final class RapporteurErreurs
{
    public function __construct(
        private MessagesUtilisateurInterface $messagesFlash,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $contexte
     */
    public function signalerBaseDeDonnees(\Throwable $exception, array $contexte = []): void
    {
        $message = match (true) {
            $exception instanceof UniqueConstraintViolationException
                => 'Cet enregistrement existe deja.',
            $exception instanceof ConnectionException
                => 'La base de donnees est momentanement injoignable. Reessayez dans un instant.',
            default
                => 'Une erreur est survenue lors de l enregistrement. Reessayez.',
        };

        $this->messagesFlash->erreur($message);
        $this->journaliser($exception, $contexte + ['categorie' => 'base_de_donnees']);
    }

    /**
     * @param array<string, mixed> $contexte
     */
    public function signalerUpload(\Throwable $exception, array $contexte = []): void
    {
        $message = 'Le fichier n a pas pu etre enregistre.';

        if ($exception instanceof FileException) {
            $detail = $exception->getMessage();
            $message = match (true) {
                str_contains($detail, 'size') => 'Le fichier est trop volumineux.',
                str_contains($detail, 'mime') => 'Ce format de fichier n est pas accepte.',
                default => $detail !== '' ? $detail : $message,
            };
        }

        $this->messagesFlash->erreur($message);
        $this->journaliser($exception, $contexte + ['categorie' => 'upload']);
    }

    /**
     * @param array<string, mixed> $contexte
     */
    public function signalerSecurite(\Throwable $exception, array $contexte = []): void
    {
        $message = match (true) {
            $exception instanceof AccessDeniedException => 'Vous n avez pas acces a cette ressource.',
            $exception instanceof InvalidCsrfTokenException => 'Session expiree. Rechargez la page et reessayez.',
            default => 'Email ou mot de passe incorrect.',
        };

        $this->messagesFlash->erreur($message);
        $this->journaliser($exception, $contexte + ['categorie' => 'securite']);
    }

    /**
     * Journalise sans rien afficher a l'utilisateur.
     *
     * @param array<string, mixed> $contexte
     */
    public function journaliser(\Throwable $exception, array $contexte = []): void
    {
        $this->logger->error($exception->getMessage(), $contexte + ['exception' => $exception]);
    }
}
