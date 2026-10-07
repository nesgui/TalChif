<?php

declare(strict_types=1);

namespace App\Service\Notification;

/**
 * Capacite d'adresser un message a l'utilisateur courant.
 *
 * Les services qui signalent une erreur dependent de ce contrat, pas du
 * transport : la session n'est qu'une implementation parmi d'autres, et un
 * test peut substituer la sienne.
 */
interface MessagesUtilisateurInterface
{
    public function succes(string $message): void;

    public function erreur(string $message): void;

    public function avertissement(string $message): void;
}
