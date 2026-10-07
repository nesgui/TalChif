<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MembreOrganisationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Appartenance d'un utilisateur a une organisation, avec son role.
 *
 * Remplace la chaine unique User::$role pour la dimension vendeur : un meme
 * compte peut etre proprietaire d'une organisation et simple valideur dans une
 * autre, ce que l'ancien modele ne permettait pas.
 */
#[ORM\Entity(repositoryClass: MembreOrganisationRepository::class)]
#[ORM\Table(name: 'membre_organisation')]
#[ORM\UniqueConstraint(name: 'uniq_membre_organisation', columns: ['organisation_id', 'utilisateur_id'])]
#[ORM\HasLifecycleCallbacks]
class MembreOrganisation
{
    /** Gere l'organisation et ses membres. */
    public const ROLE_PROPRIETAIRE = 'PROPRIETAIRE';

    /** Cree et administre les evenements. */
    public const ROLE_GESTIONNAIRE = 'GESTIONNAIRE';

    /** Scanne les billets a l'entree, sans acces aux reglages. */
    public const ROLE_CONTROLEUR = 'CONTROLEUR';

    /** @var list<string> */
    public const ROLES = [self::ROLE_PROPRIETAIRE, self::ROLE_GESTIONNAIRE, self::ROLE_CONTROLEUR];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organisation::class, inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organisation $organisation = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'appartenances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $utilisateur = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::ROLES, message: 'Role d organisation inconnu')]
    private string $role = self::ROLE_GESTIONNAIRE;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function definirDateCreation(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): static
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!\in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException("Role d organisation inconnu : {$role}");
        }

        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Le proprietaire et le gestionnaire administrent les evenements ; le
     * controleur se limite au scan.
     */
    public function peutGererEvenements(): bool
    {
        return \in_array($this->role, [self::ROLE_PROPRIETAIRE, self::ROLE_GESTIONNAIRE], true);
    }
}
