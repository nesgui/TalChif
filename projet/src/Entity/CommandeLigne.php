<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CommandeLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\Column(type: 'integer')]
    private int $quantite = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixUnitaire = null;

    #[ORM\Column(length: 10)]
    private string $typeBillet = 'SIMPLE';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
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

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): float
    {
        return (float) $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = (string) $prixUnitaire;
        return $this;
    }

    public function getSousTotal(): float
    {
        return $this->getPrixUnitaire() * $this->quantite;
    }

    public function getTypeBillet(): string
    {
        return $this->typeBillet;
    }

    public function setTypeBillet(string $typeBillet): static
    {
        $this->typeBillet = $typeBillet;
        return $this;
    }
}
