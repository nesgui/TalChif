<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Service\Tenant\ContexteTenant;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le contexte d'organisation aux gabarits.
 *
 * Evite de passer les memes variables depuis chacun des 29 controleurs du
 * tableau de bord : le selecteur se sert lui-meme.
 */
final class ExtensionTenant extends AbstractExtension
{
    public function __construct(
        private ContexteTenant $contexteTenant,
        private Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('organisations_du_membre', [$this, 'organisationsDuMembre']),
            new TwigFunction('organisation_courante', [$this, 'organisationCourante']),
        ];
    }

    /**
     * @return MembreOrganisation[]
     */
    public function organisationsDuMembre(): array
    {
        $utilisateur = $this->security->getUser();

        return $utilisateur instanceof User ? $this->contexteTenant->appartenances($utilisateur) : [];
    }

    public function organisationCourante(): ?Organisation
    {
        if ($this->contexteTenant->estEtabli()) {
            return $this->contexteTenant->organisation();
        }

        $utilisateur = $this->security->getUser();

        return $utilisateur instanceof User ? $this->contexteTenant->resoudrePour($utilisateur) : null;
    }
}
