<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Tenant\ContexteTenant;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Etablit l'organisation courante sur l'espace organisateur.
 *
 * Portee deliberement restreinte a cet espace : le catalogue public liste les
 * evenements de toutes les organisations, et l'administration doit garder une
 * vue d'ensemble. Activer le filtre globalement masquerait le catalogue.
 *
 * S'execute sur kernel.controller, donc apres l'authentification : le jeton de
 * securite est disponible.
 */
final class TenantSubscriber implements EventSubscriberInterface
{
    /** Prefixes d'URL cloisonnes. */
    private const ESPACES_CLOISONNES = ['/organisateur', '/api/organisateur'];

    public function __construct(
        private ContexteTenant $contexteTenant,
        private Security $security,
        private AuthorizationCheckerInterface $autorisation,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $chemin = $event->getRequest()->getPathInfo();
        if (!$this->estCloisonne($chemin)) {
            return;
        }

        // Un administrateur garde une vue transverse.
        if ($this->autorisation->isGranted('ROLE_ADMIN')) {
            return;
        }

        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof User) {
            return;
        }

        $organisation = $this->contexteTenant->resoudrePour($utilisateur);
        if ($organisation !== null) {
            $this->contexteTenant->etablir($organisation);
        }
    }

    /**
     * Le contexte ne doit pas fuir sur la requete suivante : l'EntityManager
     * est partage, notamment sous un worker a longue duree de vie.
     */
    public function onFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->contexteTenant->liberer();
        }
    }

    private function estCloisonne(string $chemin): bool
    {
        foreach (self::ESPACES_CLOISONNES as $prefixe) {
            if (str_starts_with($chemin, $prefixe)) {
                return true;
            }
        }

        return false;
    }
}
