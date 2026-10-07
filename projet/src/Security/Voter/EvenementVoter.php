<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Evenement;
use App\Entity\MembreOrganisation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Centralise la regle d'acces a un evenement.
 *
 * L'acces vient de l'appartenance a l'organisation proprietaire, et non plus du
 * seul fait d'etre le compte createur : plusieurs membres peuvent gerer les
 * memes evenements, avec des droits distincts selon leur role.
 *
 * - CONSULTER : tout membre de l'organisation, controleur inclus
 * - MODIFIER  : proprietaire et gestionnaire uniquement
 *
 * Un administrateur passe toujours. Le createur historique reste autorise, pour
 * les evenements anterieurs a la migration des organisations.
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
        if ($this->estCreateur($subject, $utilisateur)) {
            return true;
        }

        $organisation = $subject->getOrganisation();
        if ($organisation === null) {
            return false;
        }

        $role = $utilisateur->roleDansOrganisation($organisation);
        if ($role === null) {
            return false;
        }

        if ($attribute === self::CONSULTER) {
            return true;
        }

        return \in_array(
            $role,
            [MembreOrganisation::ROLE_PROPRIETAIRE, MembreOrganisation::ROLE_GESTIONNAIRE],
            true
        );
    }

    private function estCreateur(Evenement $evenement, User $utilisateur): bool
    {
        $organisateur = $evenement->getOrganisateur();

        return $organisateur !== null && $organisateur->getId() === $utilisateur->getId();
    }
}
