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
    public const STATUT_PROCESSING = 'Processing';
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

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $depositId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $referenceTransactionClient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $capturePreuvePaiement = null;

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
        return $this->statut === self::STATUT_PENDING || $this->statut === 'Pending';
    }

    public function isProcessing(): bool
    {
        return $this->statut === self::STATUT_PROCESSING;
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

    public function marquerEnTraitement(string $depositId): void
    {
        $this->statut = self::STATUT_PROCESSING;
        $this->depositId = $depositId;
    }

    public function estExpiree(): bool
    {
        if (!$this->dateExpiration) {
            return false;
        }
        
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->dateExpiration < $now;
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

    /**
     * Marquer la commande comme payée.
     * Logique métier : vérifie que la commande est en attente avant de la valider.
     *
     * @throws \RuntimeException
     */
    public function marquerPayee(?User $validateur = null): void
    {
        if (!$this->isPending() && !$this->isProcessing()) {
            throw new \RuntimeException(
                "La commande {$this->reference} n'est pas en attente de paiement."
            );
        }

        if ($this->estExpiree()) {
            throw new \RuntimeException(
                "La commande {$this->reference} a expiré."
            );
        }

        $this->statut = self::STATUT_PAID;
        $this->validePar = $validateur;
        $this->dateValidation = new \DateTimeImmutable();
    }

    /**
     * Marquer la commande comme expirée.
     */
    public function marquerExpiree(): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException(
                "Seules les commandes en attente peuvent expirer."
            );
        }

        $this->statut = self::STATUT_EXPIRED;
    }

    /**
     * Marquer la commande comme rejetée.
     */
    public function marquerRejetee(?User $validateur = null): void
    {
        if (!$this->isPending() && !$this->isProcessing()) {
            throw new \RuntimeException(
                "La commande {$this->reference} n'est pas en attente."
            );
        }

        $this->statut = self::STATUT_REJECTED;
        $this->validePar = $validateur;
        $this->dateValidation = new \DateTimeImmutable();
    }

    /**
     * Vérifie si la commande peut être validée.
     */
    public function peutEtreValidee(): bool
    {
        $isPending = $this->isPending();
        $isProcessing = $this->isProcessing();
        $estExpiree = $this->estExpiree();
        
        return ($isPending || $isProcessing) && !$estExpiree;
    }

    /**
     * Vérifie si la commande est dans le délai de validation.
     */
    public function estDansDelaiValidation(): bool
    {
        if (!$this->dateExpiration) {
            return false;
        }
        return new \DateTimeImmutable() < $this->dateExpiration;
    }

    /**
     * Annuler la commande.
     */
    public function annuler(): void
    {
        if ($this->isPaid()) {
            throw new \RuntimeException(
                "Impossible d'annuler une commande déjà payée."
            );
        }

        $this->statut = self::STATUT_CANCELLED;
    }

    /**
     * Obtenir le temps restant avant expiration (en minutes).
     */
    public function getTempsRestantMinutes(): ?int
    {
        if (!$this->dateExpiration || $this->estExpiree()) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $this->dateExpiration->getTimestamp() - $now->getTimestamp();
        return (int) ceil($diff / 60);
    }

    public function getDepositId(): ?string
    {
        return $this->depositId;
    }

    public function setDepositId(?string $depositId): self
    {
        $this->depositId = $depositId;
        return $this;
    }

    public function getReferenceTransactionClient(): ?string
    {
        return $this->referenceTransactionClient;
    }

    public function setReferenceTransactionClient(?string $referenceTransactionClient): self
    {
        $this->referenceTransactionClient = $referenceTransactionClient;
        return $this;
    }

    public function getCapturePreuvePaiement(): ?string
    {
        return $this->capturePreuvePaiement;
    }

    public function setCapturePreuvePaiement(?string $capturePreuvePaiement): self
    {
        $this->capturePreuvePaiement = $capturePreuvePaiement;
        return $this;
    }
}
