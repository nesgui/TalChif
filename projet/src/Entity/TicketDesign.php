<?php

namespace App\Entity;

use App\Repository\TicketDesignRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketDesignRepository::class)]
#[ORM\Table(name: 'ticket_design')]
#[ORM\UniqueConstraint(name: 'uniq_ticket_design_evenement_type', columns: ['evenement_id', 'type_billet'])]
#[ORM\HasLifecycleCallbacks]
class TicketDesign
{
    public const TYPE_SIMPLE = 'SIMPLE';
    public const TYPE_VIP = 'VIP';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Organisation proprietaire. Cle du cloisonnement automatique :
     * le filtre Doctrine TenantFilter s'appuie sur cette colonne.
     */
    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organisation $organisation = null;

    #[ORM\ManyToOne(inversedBy: 'ticketDesigns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\Column(length: 10)]
    private ?string $typeBillet = self::TYPE_SIMPLE;

    #[ORM\Column(length: 500)]
    private ?string $designPath = null;

    #[ORM\Column(type: 'integer')]
    private ?int $designWidth = null;

    #[ORM\Column(type: 'integer')]
    private ?int $designHeight = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTypeBillet(): ?string
    {
        return $this->typeBillet;
    }

    public function setTypeBillet(string $typeBillet): static
    {
        $this->typeBillet = $typeBillet;
        return $this;
    }

    public function getDesignPath(): ?string
    {
        return $this->designPath;
    }

    public function setDesignPath(string $designPath): static
    {
        $this->designPath = $designPath;
        return $this;
    }

    public function getDesignWidth(): ?int
    {
        return $this->designWidth;
    }

    public function setDesignWidth(int $designWidth): static
    {
        $this->designWidth = $designWidth;
        return $this;
    }

    public function getDesignHeight(): ?int
    {
        return $this->designHeight;
    }

    public function setDesignHeight(int $designHeight): static
    {
        $this->designHeight = $designHeight;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
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

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): static
    {
        $this->organisation = $organisation;

        return $this;
    }
}
