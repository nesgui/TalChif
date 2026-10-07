<?php

declare(strict_types=1);

namespace App\Service\Tenant;

use App\Entity\MembreOrganisation;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\MembreOrganisationRepository;
use App\Repository\OrganisationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie des organisations et de leurs membres.
 */
final class ServiceOrganisation
{
    public function __construct(
        private OrganisationRepository $organisationRepository,
        private MembreOrganisationRepository $membreRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Organisation de cet utilisateur, creee au besoin.
     *
     * Permet a un compte existant de continuer a publier sans etape
     * d'onboarding : la premiere action cree son organisation et l'en rend
     * proprietaire.
     */
    public function assurerPour(User $utilisateur): Organisation
    {
        $appartenances = $this->membreRepository->findParUtilisateur($utilisateur);
        if ($appartenances !== []) {
            return $appartenances[0]->getOrganisation();
        }

        $libelle = trim((string) $utilisateur->getNom());
        if ($libelle === '') {
            $libelle = (string) $utilisateur->getEmail();
        }

        $organisation = new Organisation();
        $organisation->setNom($libelle);
        $organisation->setSlug($this->organisationRepository->genererSlugUnique($libelle));
        $organisation->setActif(true);
        $this->entityManager->persist($organisation);

        $this->rattacher($organisation, $utilisateur, MembreOrganisation::ROLE_PROPRIETAIRE);
        $this->entityManager->flush();

        return $organisation;
    }

    /**
     * Ajoute un membre, ou met a jour son role s'il est deja rattache.
     */
    public function rattacher(Organisation $organisation, User $utilisateur, string $role): MembreOrganisation
    {
        $membre = $this->membreRepository->findAppartenance($utilisateur, $organisation)
            ?? new MembreOrganisation();

        $membre->setOrganisation($organisation);
        $membre->setRole($role);

        // Les deux cotes sont synchronises : l'appartenance doit etre visible
        // des la requete courante, pour getRoles() comme pour les voters.
        $utilisateur->ajouterAppartenance($membre);
        $organisation->ajouterMembre($membre);

        $this->entityManager->persist($membre);

        return $membre;
    }

    public function detacher(Organisation $organisation, User $utilisateur): void
    {
        $membre = $this->membreRepository->findAppartenance($utilisateur, $organisation);
        if ($membre === null) {
            return;
        }

        if ($membre->getRole() === MembreOrganisation::ROLE_PROPRIETAIRE
            && $this->comptePropietaires($organisation) <= 1) {
            throw new \RuntimeException('Une organisation doit conserver au moins un proprietaire.');
        }

        $utilisateur->retirerAppartenance($membre);
        $this->entityManager->remove($membre);
        $this->entityManager->flush();
    }

    private function comptePropietaires(Organisation $organisation): int
    {
        $compte = 0;
        foreach ($organisation->getMembres() as $membre) {
            if ($membre->getRole() === MembreOrganisation::ROLE_PROPRIETAIRE) {
                $compte++;
            }
        }

        return $compte;
    }
}
