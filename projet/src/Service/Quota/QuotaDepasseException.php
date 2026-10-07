<?php

declare(strict_types=1);

namespace App\Service\Quota;

/**
 * Une limite du plan est atteinte : l'action est refusee.
 *
 * Porte le message destine a l'utilisateur ainsi que les elements de mesure,
 * pour que l'interface puisse afficher « 3 sur 3 » sans recalculer.
 */
final class QuotaDepasseException extends \RuntimeException
{
    public function __construct(
        public readonly string $quota,
        public readonly int $consomme,
        public readonly int $limite,
        string $message,
    ) {
        parent::__construct($message);
    }
}
