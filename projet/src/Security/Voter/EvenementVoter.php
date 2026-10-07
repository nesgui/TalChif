<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Evenement;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Centralise la regle d'acces a un evenement : son organisateur ou un administrateur.
 *
 * Remplace les controles « $evenement->getOrganisateur() !== $this->getUser() »
 * dupliques dans les controleurs (SOLID : une seule source de verite).
 *
 * @extends Voter<string, Evenement>
 */
final class EvenementVoter extends Voter
{
    public const CONSULTER = 'VIEW';
    public const MODIFIER = 'EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::CONSULTER, self::MODIFIER], true)
            && $subject instanceof Evenement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof User) {
            return false;
        }

        if ($utilisateur->isAdmin()) {
            return true;
        }

        /** @var Evenement $subject */
        $organisateur = $subject->getOrganisateur();

        return $organisateur !== null && $organisateur->getId() === $utilisateur->getId();
    }
}
