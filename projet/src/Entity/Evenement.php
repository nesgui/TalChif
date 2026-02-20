<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 3, max: 255)]
    #[Assert\Regex(pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u', message: 'Le nom ne doit pas contenir de caractères invalides')]
    private ?string $nom = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, max: 10000)]
    #[Assert\Regex(pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u', message: 'La description ne doit pas contenir de caractères invalides')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateEvenement = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u', message: 'Le lieu ne doit pas contenir de caractères invalides')]
    private ?string $lieu = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u', message: 'L\'adresse ne doit pas contenir de caractères invalides')]
    private ?string $adresse = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u', message: 'La ville ne doit pas contenir de caractères invalides')]
    private ?string $ville = null;

    #[ORM\Column(type: 'integer')]
    private ?int $placesDisponibles = null;

    #[ORM\Column(type: 'integer')]
    private ?int $placesVendues = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixSimple = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixVip = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $affichePrincipale = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $autresAffiches = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageBillet = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isValide = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'evenementsOrganises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organisateur = null;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Billet::class, cascade: ['persist', 'remove'])]
    private Collection $billets;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: TicketDesign::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ticketDesigns;

    #[ORM\Column(type: 'boolean')]
    private bool $organisateurPaye = false;

    public function __construct()
    {
        $this->billets = new ArrayCollection();
        $this->ticketDesigns = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getDateEvenement(): ?\DateTimeImmutable
    {
        return $this->dateEvenement;
    }

    public function setDateEvenement(\DateTimeImmutable $dateEvenement): static
    {
        $this->dateEvenement = $dateEvenement;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPlacesDisponibles(): ?int
    {
        return $this->placesDisponibles;
    }

    public function setPlacesDisponibles(int $placesDisponibles): static
    {
        $this->placesDisponibles = $placesDisponibles;

        return $this;
    }

    public function getPlacesVendues(): ?int
    {
        return $this->placesVendues;
    }

    public function setPlacesVendues(int $placesVendues): static
    {
        $this->placesVendues = $placesVendues;

        return $this;
    }

    public function getPrixSimple(): ?float
    {
        return $this->prixSimple ? (float) $this->prixSimple : null;
    }

    public function setPrixSimple(float $prixSimple): static
    {
        $this->prixSimple = (string) $prixSimple;

        return $this;
    }

    public function getPrixVip(): ?float
    {
        return $this->prixVip ? (float) $this->prixVip : null;
    }

    public function setPrixVip(?float $prixVip): static
    {
        $this->prixVip = $prixVip ? (string) $prixVip : null;

        return $this;
    }

    public function getAffichePrincipale(): ?string
    {
        return $this->affichePrincipale;
    }

    public function setAffichePrincipale(?string $affichePrincipale): static
    {
        $this->affichePrincipale = $affichePrincipale;

        return $this;
    }

    public function getAutresAffiches(): ?array
    {
        return $this->autresAffiches;
    }

    public function setAutresAffiches(?array $autresAffiches): static
    {
        $this->autresAffiches = $autresAffiches;

        return $this;
    }

    public function getImageBillet(): ?string
    {
        return $this->imageBillet;
    }

    public function setImageBillet(?string $imageBillet): static
    {
        $this->imageBillet = $imageBillet;

        return $this;
    }

    /** Indique si l'événement est actif (visible et en vente). */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getOrganisateur(): ?User
    {
        return $this->organisateur;
    }

    public function setOrganisateur(?User $organisateur): static
    {
        $this->organisateur = $organisateur;

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
            $billet->setEvenement($this);
        }

        return $this;
    }

    public function removeBillet(Billet $billet): static
    {
        if ($this->billets->removeElement($billet)) {
            // set the owning side to null (unless already changed)
            if ($billet->getEvenement() === $this) {
                $billet->setEvenement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TicketDesign>
     */
    public function getTicketDesigns(): Collection
    {
        return $this->ticketDesigns;
    }

    public function addTicketDesign(TicketDesign $ticketDesign): static
    {
        if (!$this->ticketDesigns->contains($ticketDesign)) {
            $this->ticketDesigns->add($ticketDesign);
            $ticketDesign->setEvenement($this);
        }

        return $this;
    }

    public function removeTicketDesign(TicketDesign $ticketDesign): static
    {
        if ($this->ticketDesigns->removeElement($ticketDesign)) {
            if ($ticketDesign->getEvenement() === $this) {
                $ticketDesign->setEvenement(null);
            }
        }

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

    public function getPlacesRestantes(): int
    {
        return max(0, $this->placesDisponibles - $this->placesVendues);
    }

    public function isComplet(): bool
    {
        return $this->getPlacesRestantes() <= 0;
    }

    public function isOrganisateurPaye(): bool
    {
        return $this->organisateurPaye;
    }

    public function setOrganisateurPaye(bool $organisateurPaye): static
    {
        $this->organisateurPaye = $organisateurPaye;
        return $this;
    }

    public function hasVip(): bool
    {
        return $this->prixVip !== null && (float) $this->prixVip > 0;
    }
}
