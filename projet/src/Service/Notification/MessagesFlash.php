<?php

declare(strict_types=1);

namespace App\Service\Notification;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Depose des messages flash a destination de l'utilisateur.
 *
 * Tolere l'absence de session : ces methodes sont appelees depuis des blocs
 * catch qui peuvent s'executer hors contexte HTTP (commande console, worker).
 * Un message perdu ne doit jamais masquer l'erreur d'origine.
 */
final class MessagesFlash implements MessagesUtilisateurInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function succes(string $message): void
    {
        $this->deposer('success', $message);
    }

    public function erreur(string $message): void
    {
        $this->deposer('error', $message);
    }

    public function avertissement(string $message): void
    {
        $this->deposer('warning', $message);
    }

    private function deposer(string $type, string $message): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (\Throwable) {
            return;
        }

        $session->getFlashBag()->add($type, $message);
    }
}
