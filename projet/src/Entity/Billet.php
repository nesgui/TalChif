<?php

namespace App\Entity;

use App\Repository\BilletRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BilletRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Billet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $qrCode = null;

    #[ORM\Column(length: 20)]
    private ?string $type = 'SIMPLE'; // SIMPLE, VIP

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prix = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isValide = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isUtilise = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateUtilisation = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'billets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(inversedBy: 'billets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $organisateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validePar = null;

    #[ORM\Column(length: 50)]
    private ?string $statutPaiement = 'EN_ATTENTE'; // EN_ATTENTE, PAYE, REMBOURSE

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $renderedPngPath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(string $qrCode): static
    {
        $this->qrCode = $qrCode;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix ? (float) $this->prix : null;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = (string) $prix;

        return $this;
    }

    public function isValide(): bool
    {
        return $this->isValide;
    }

    public function setIsValide(bool $isValide): static
    {
        $this->isValide = $isValide;

        return $this;
    }

    public function isUtilise(): bool
    {
        return $this->isUtilise;
    }

    public function setIsUtilise(bool $isUtilise): static
    {
        $this->isUtilise = $isUtilise;

        return $this;
    }

    public function getDateUtilisation(): ?\DateTimeImmutable
    {
        return $this->dateUtilisation;
    }

    public function setDateUtilisation(?\DateTimeImmutable $dateUtilisation): static
    {
        $this->dateUtilisation = $dateUtilisation;

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

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;

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

    public function getOrganisateur(): ?User
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?User $organisateur): static
    {
        $this->organisateur = $organisateur;

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getStatutPaiement(): ?string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;

        return $this;
    }

    public function getRenderedPngPath(): ?string
    {
        return $this->renderedPngPath;
    }

    public function setRenderedPngPath(?string $renderedPngPath): static
    {
        $this->renderedPngPath = $renderedPngPath;

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

    public function isSimple(): bool
    {
        return $this->type === 'SIMPLE';
    }

    public function isVip(): bool
    {
        return $this->type === 'VIP';
    }

    public function isPaye(): bool
    {
        return $this->statutPaiement === 'PAYE';
    }

    public function isEnAttente(): bool
    {
        return $this->statutPaiement === 'EN_ATTENTE';
    }

    public function isRembourse(): bool
    {
        return $this->statutPaiement === 'REMBOURSE';
    }

    public function marquerCommeUtilise(): void
    {
        $this->isUtilise = true;
        $this->dateUtilisation = new \DateTimeImmutable();
    }

    public function validerPaiement(): void
    {
        $this->statutPaiement = 'PAYE';
        $this->isValide = true;
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

    public function setUtilise(bool $utilise): static
    {
        $this->isUtilise = $utilise;

        return $this;
    }

    public function annuler(): void
    {
        $this->statutPaiement = 'REMBOURSE';
        $this->isValide = false;
    }

    /**
     * Utiliser le billet (scan à l'entrée).
     * Logique métier : vérifie que le billet est utilisable avant de le marquer comme utilisé.
     *
     * @throws \RuntimeException
     */
    public function utiliser(User $validateur): void
    {
        if (!$this->estUtilisable()) {
            throw new \RuntimeException(
                "Le billet {$this->qrCode} ne peut pas être utilisé. " .
                "Statut : " . $this->getStatutUtilisation()
            );
        }

        $this->isUtilise = true;
        $this->dateUtilisation = new \DateTimeImmutable();
        $this->validePar = $validateur;
    }

    /**
     * Vérifie si le billet peut être utilisé.
     */
    public function peutEtreUtilise(): bool
    {
        return $this->isValide 
            && !$this->isUtilise 
            && $this->isPaye();
    }

    /**
     * Vérifie si le billet est utilisable (alias de peutEtreUtilise).
     */
    public function estUtilisable(): bool
    {
        return $this->peutEtreUtilise();
    }

    /**
     * Invalider le billet (fraude, annulation, etc.).
     */
    public function invalider(string $raison = ''): void
    {
        if ($this->isUtilise) {
            throw new \RuntimeException(
                "Impossible d'invalider un billet déjà utilisé."
            );
        }

        $this->isValide = false;
    }

    /**
     * Rembourser le billet.
     */
    public function rembourser(): void
    {
        if ($this->isUtilise) {
            throw new \RuntimeException(
                "Impossible de rembourser un billet déjà utilisé."
            );
        }

        $this->statutPaiement = 'REMBOURSE';
        $this->isValide = false;
    }

    /**
     * Obtenir le statut d'utilisation du billet (pour affichage).
     */
    public function getStatutUtilisation(): string
    {
        if (!$this->isValide) {
            return 'Invalide';
        }
        if ($this->isUtilise) {
            return 'Utilisé';
        }
        if (!$this->isPaye()) {
            return 'Paiement en attente';
        }
        return 'Valide';
    }

    /**
     * Vérifie si le billet appartient à un utilisateur.
     */
    public function appartientA(User $user): bool
    {
        return $this->client && $this->client->getId() === $user->getId();
    }

    /**
     * Vérifie si le billet est pour un événement donné.
     */
    public function estPourEvenement(Evenement $evenement): bool
    {
        return $this->evenement && $this->evenement->getId() === $evenement->getId();
    }
}
