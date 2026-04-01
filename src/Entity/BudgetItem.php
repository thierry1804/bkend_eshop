<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BudgetItemRepository;
use App\Utils\BudgetPeriodicite;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetItemRepository::class)]
#[ORM\Table(name: 'budget_items')]
#[ORM\HasLifecycleCallbacks]
class BudgetItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $nom;

    #[ORM\ManyToOne(inversedBy: 'budgetItems')]
    #[ORM\JoinColumn(nullable: false)]
    private Categorie $categorie;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['MOIS', 'SEMAINE', '2_SEMAINES', 'TRIMESTRE', 'ANNEE'])]
    private string $periodicite = 'MOIS';

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Positive]
    private float $quantite = 1;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $prixUnitaire = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Positive]
    private int $frequence = 1;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCategorie(): Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getPeriodicite(): string
    {
        return $this->periodicite;
    }

    public function setPeriodicite(string $periodicite): static
    {
        $this->periodicite = $periodicite;

        return $this;
    }

    public function getQuantite(): float
    {
        return $this->quantite;
    }

    public function setQuantite(float $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getPrixUnitaire(): int
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(int $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getFrequence(): int
    {
        return $this->frequence;
    }

    public function setFrequence(int $frequence): static
    {
        $this->frequence = $frequence;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Montant d'une occurrence (quantité × prix unitaire), en Ariary.
     */
    public function getMontant(): int
    {
        return (int) round($this->quantite * $this->prixUnitaire);
    }

    /**
     * Équivalent mensuel : arrondi( quantite × prixUnitaire × occurrencesParMois(periodicite, frequence) ).
     */
    public function getMontantMensuel(): float
    {
        $occ = BudgetPeriodicite::occurrencesParMois($this->periodicite, $this->frequence);

        return (float) (int) round($this->quantite * $this->prixUnitaire * $occ);
    }
}
