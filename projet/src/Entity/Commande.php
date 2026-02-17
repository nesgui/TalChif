<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Commande
{
    public const STATUT_PENDING = 'Pending Payment';
    public const STATUT_PAID = 'Paid';
    public const STATUT_EXPIRED = 'Expired';
    public const STATUT_REJECTED = 'Rejected';
    public const STATUT_CANCELLED = 'Cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $montantTotal = null;

    #[ORM\Column(length: 20)]
    private ?string $numeroClient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\Column(length: 50)]
    private string $statut = self::STATUT_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateExpiration = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20)]
    private ?string $methodePaiement = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $commissionPlateforme = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montantNetOrganisateur = '0';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validePar = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    #[ORM\Column(type: 'integer')]
    private int $tentativeValidation = 0;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getMontantTotal(): float
    {
        return (float) $this->montantTotal;
    }

    public function setMontantTotal(float $montantTotal): static
    {
        $this->montantTotal = (string) $montantTotal;
        return $this;
    }

    public function getNumeroClient(): ?string
    {
        return $this->numeroClient;
    }

    public function setNumeroClient(string $numeroClient): static
    {
        $this->numeroClient = $numeroClient;
        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateExpiration(): ?\DateTimeImmutable
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(\DateTimeImmutable $dateExpiration): static
    {
        $this->dateExpiration = $dateExpiration;
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

    public function getMethodePaiement(): ?string
    {
        return $this->methodePaiement;
    }

    public function setMethodePaiement(string $methodePaiement): static
    {
        $this->methodePaiement = $methodePaiement;
        return $this;
    }

    public function getCommissionPlateforme(): float
    {
        return (float) $this->commissionPlateforme;
    }

    public function setCommissionPlateforme(float $commissionPlateforme): static
    {
        $this->commissionPlateforme = (string) $commissionPlateforme;
        return $this;
    }

    public function getMontantNetOrganisateur(): float
    {
        return (float) $this->montantNetOrganisateur;
    }

    public function setMontantNetOrganisateur(float $montantNetOrganisateur): static
    {
        $this->montantNetOrganisateur = (string) $montantNetOrganisateur;
        return $this;
    }

    public function getValidePar(): ?User
    {
        return $this->validePar;
    }

    public function setValidePar(?User $validePar): static
    {
        $this->validePar = $validePar;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getTentativeValidation(): int
    {
        return $this->tentativeValidation;
    }

    public function incrementerTentativeValidation(): static
    {
        $this->tentativeValidation++;
        return $this;
    }

    public function setTentativeValidation(int $tentativeValidation): static
    {
        $this->tentativeValidation = $tentativeValidation;
        return $this;
    }

    /**
     * @return Collection<int, CommandeLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(CommandeLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }
        return $this;
    }

    public function isPending(): bool
    {
        return $this->statut === self::STATUT_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->statut === self::STATUT_PAID;
    }

    public function isExpired(): bool
    {
        return $this->statut === self::STATUT_EXPIRED;
    }

    public function isRejected(): bool
    {
        return $this->statut === self::STATUT_REJECTED;
    }

    public function estExpiree(): bool
    {
        return $this->dateExpiration && $this->dateExpiration < new \DateTimeImmutable();
    }

    public function getPremierEvenement(): ?Evenement
    {
        $premiere = $this->lignes->first();
        return $premiere ? $premiere->getEvenement() : null;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
