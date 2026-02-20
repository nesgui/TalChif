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

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $qrX = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $qrY = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $qrW = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $qrH = null;

    #[ORM\Column(length: 7)]
    private ?string $markerColor = '#0d1321';

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

    public function getQrX(): ?int
    {
        return $this->qrX;
    }

    public function setQrX(?int $qrX): static
    {
        $this->qrX = $qrX;
        return $this;
    }

    public function getQrY(): ?int
    {
        return $this->qrY;
    }

    public function setQrY(?int $qrY): static
    {
        $this->qrY = $qrY;
        return $this;
    }

    public function getQrW(): ?int
    {
        return $this->qrW;
    }

    public function setQrW(?int $qrW): static
    {
        $this->qrW = $qrW;
        return $this;
    }

    public function getQrH(): ?int
    {
        return $this->qrH;
    }

    public function setQrH(?int $qrH): static
    {
        $this->qrH = $qrH;
        return $this;
    }

    public function getMarkerColor(): ?string
    {
        return $this->markerColor;
    }

    public function setMarkerColor(string $markerColor): static
    {
        $this->markerColor = $markerColor;
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

    public function hasManualQrZone(): bool
    {
        return $this->qrX !== null
            && $this->qrY !== null
            && $this->qrW !== null
            && $this->qrH !== null;
    }
}
