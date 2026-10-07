<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Offre souscrite par une organisation : ses limites d'usage.
 *
 * Les quotas sont bloquants : une limite atteinte refuse l'action, elle ne
 * declenche pas de facturation a l'usage. Une limite a null est illimitee, ce
 * qui permet un plan sur mesure sans colonne supplementaire.
 */
#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plan')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code de plan existe deja.')]
class Plan
{
    /** Plan applique a defaut de souscription. */
    public const CODE_DECOUVERTE = 'DECOUVERTE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z0-9_]+$/', message: 'Code en majuscules, chiffres et tirets bas.')]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $libelle = null;

    /** Evenements actifs simultanes. Null : illimite. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $limiteEvenementsActifs = null;

    /** Billets emis par mois calendaire. Null : illimite. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $limiteBilletsParMois = null;

    /** Membres de l'equipe, proprietaire inclus. Null : illimite. */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $limiteMembres = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Organisation> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: Organisation::class)]
    private Collection $organisations;

    public function __construct()
    {
        $this->organisations = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = mb_strtoupper($code);

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getLimiteEvenementsActifs(): ?int
    {
        return $this->limiteEvenementsActifs;
    }

    public function setLimiteEvenementsActifs(?int $limite): static
    {
        $this->limiteEvenementsActifs = $limite;

        return $this;
    }

    public function getLimiteBilletsParMois(): ?int
    {
        return $this->limiteBilletsParMois;
    }

    public function setLimiteBilletsParMois(?int $limite): static
    {
        $this->limiteBilletsParMois = $limite;

        return $this;
    }

    public function getLimiteMembres(): ?int
    {
        return $this->limiteMembres;
    }

    public function setLimiteMembres(?int $limite): static
    {
        $this->limiteMembres = $limite;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Organisation> */
    public function getOrganisations(): Collection
    {
        return $this->organisations;
    }

    public function estIllimite(): bool
    {
        return $this->limiteEvenementsActifs === null
            && $this->limiteBilletsParMois === null
            && $this->limiteMembres === null;
    }
}
