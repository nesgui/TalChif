<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * Tableau des rôles Symfony (dérivé de la propriété role).
     * @var array<int, string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[\p{L}\p{M}\s\-\']+$/u', message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[\p{L}\p{M}\s\-\']+$/u', message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes')]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^[\d\s+\-()]*$/', message: 'Le téléphone ne peut contenir que des chiffres, espaces, +, - et parenthèses')]
    private ?string $telephone = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $role = 'CLIENT'; // CLIENT, ORGANISATEUR, ADMIN

    #[ORM\OneToMany(mappedBy: 'organisateur', targetEntity: Evenement::class)]
    private Collection $evenementsOrganises;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Billet::class)]
    private Collection $billets;

    public function __construct()
    {
        $this->evenementsOrganises = new ArrayCollection();
        $this->billets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Rôles Symfony dérivés uniquement de la propriété role (source de vérité).
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roleSymfony = 'ROLE_' . $this->role;
        return array_unique(['ROLE_USER', $roleSymfony]);
    }

    /**
     * Définit les rôles Symfony. Le premier rôle métier trouvé (ROLE_ADMIN, ROLE_ORGANISATEUR, ROLE_CLIENT)
     * est utilisé pour mettre à jour la propriété role.
     */
    public function setRoles(array $roles): static
    {
        foreach (['ROLE_ADMIN', 'ROLE_ORGANISATEUR', 'ROLE_CLIENT'] as $r) {
            if (in_array($r, $roles, true)) {
                $this->role = str_replace('ROLE_', '', $r);
                return $this;
            }
        }
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        $this->roles = ['ROLE_' . $role];
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getFullName(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function isClient(): bool
    {
        return $this->role === 'CLIENT';
    }

    public function isOrganisateur(): bool
    {
        return $this->role === 'ORGANISATEUR';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenementsOrganises(): Collection
    {
        return $this->evenementsOrganises;
    }

    public function addEvenementOrganise(Evenement $evenement): static
    {
        if (!$this->evenementsOrganises->contains($evenement)) {
            $this->evenementsOrganises->add($evenement);
            $evenement->setOrganisateur($this);
        }

        return $this;
    }

    public function removeEvenementOrganise(Evenement $evenement): static
    {
        if ($this->evenementsOrganises->removeElement($evenement)) {
            // set the owning side to null (unless already changed)
            if ($evenement->getOrganisateur() === $this) {
                $evenement->setOrganisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Billet>
     */
    public function getBillets(): Collection
    {
        return $this->billets;
    }

    public function addBillet(Billet $billet): static
    {
        if (!$this->billets->contains($billet)) {
            $this->billets->add($billet);
            $billet->setClient($this);
        }

        return $this;
    }

    public function removeBillet(Billet $billet): static
    {
        if ($this->billets->removeElement($billet)) {
            // set the owning side to null (unless already changed)
            if ($billet->getClient() === $this) {
                $billet->setClient(null);
            }
        }

        return $this;
    }
}
