<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tenant de la plateforme : l'entite qui vend des billets.
 *
 * Jusqu'ici le tenant etait une ligne « utilisateur » portant le role
 * ORGANISATEUR, ce qui interdisait plusieurs membres par vendeur et un taux de
 * commission par vendeur. L'organisation porte desormais ces deux dimensions,
 * et sert de cle au cloisonnement automatique des donnees.
 */
#[ORM\Entity(repositoryClass: OrganisationRepository::class)]
#[ORM\Table(name: 'organisation')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Une organisation utilise deja ce slug.')]
class Organisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le nom de l organisation est obligatoire')]
    #[Assert\Length(min: 2, max: 180)]
    private ?string $nom = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    /**
     * Taux de commission propre a cette organisation.
     * Null : on applique le taux de la plateforme.
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 0.9999)]
    private ?string $tauxCommission = null;

    /**
     * Offre souscrite. Null : plan par defaut applique par ServiceQuotas.
     */
    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'organisations')]
    private ?Plan $plan = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, MembreOrganisation> */
    #[ORM\OneToMany(mappedBy: 'organisation', targetEntity: MembreOrganisation::class, cascade: ['persist', 'remove'])]
    private Collection $membres;

    /** @var Collection<int, Evenement> */
    #[ORM\OneToMany(mappedBy: 'organisation', targetEntity: Evenement::class)]
    private Collection $evenements;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->evenements = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function definirDateCreation(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    /**
     * Taux propre a l'organisation, ou null si elle suit celui de la plateforme.
     */
    public function getTauxCommission(): ?float
    {
        return $this->tauxCommission === null ? null : (float) $this->tauxCommission;
    }

    public function setTauxCommission(?float $taux): static
    {
        $this->tauxCommission = $taux === null ? null : number_format($taux, 4, '.', '');

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, MembreOrganisation> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function ajouterMembre(MembreOrganisation $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setOrganisation($this);
        }

        return $this;
    }

    /** @return Collection<int, Evenement> */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    /**
     * Role de cet utilisateur dans l'organisation, null s'il n'en est pas membre.
     */
    public function roleDe(User $utilisateur): ?string
    {
        foreach ($this->membres as $membre) {
            if ($membre->getUtilisateur()?->getId() === $utilisateur->getId()) {
                return $membre->getRole();
            }
        }

        return null;
    }

    public function compte(User $utilisateur): bool
    {
        return $this->roleDe($utilisateur) !== null;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }
}
