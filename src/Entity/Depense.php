<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DepenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depenses')]
#[ORM\Index(columns: ['date'], name: 'idx_depense_date')]
#[ORM\Index(columns: ['categorie_code'], name: 'idx_depense_categorie')]
#[ORM\HasLifecycleCallbacks]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $produit;

    #[ORM\ManyToOne(targetEntity: BudgetItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?BudgetItem $budgetItem = null;

    #[ORM\Column(name: 'categorie_code', length: 50)]
    private string $categorieCode = '';

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Positive]
    private float $quantite = 1;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unite = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $prixUnitaire = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $montant = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function computeMontant(): void
    {
        $this->montant = (int) round($this->quantite * $this->prixUnitaire);
        if ($this->budgetItem !== null && '' === trim($this->categorieCode)) {
            $this->categorieCode = $this->budgetItem->getCategorie()->getCode();
        }
    }

    #[Assert\Callback]
    public function validateCategorieOuBudget(ExecutionContextInterface $context): void
    {
        if ('' === trim($this->categorieCode) && null === $this->budgetItem) {
            $context->buildViolation('Renseigner categorieCode ou lier un budgetItem.')
                ->atPath('categorieCode')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getProduit(): string
    {
        return $this->produit;
    }

    public function setProduit(string $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getBudgetItem(): ?BudgetItem
    {
        return $this->budgetItem;
    }

    public function setBudgetItem(?BudgetItem $budgetItem): static
    {
        $this->budgetItem = $budgetItem;

        return $this;
    }

    public function getCategorieCode(): string
    {
        return $this->categorieCode;
    }

    public function setCategorieCode(string $categorieCode): static
    {
        $this->categorieCode = $categorieCode;

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

    public function getMontant(): int
    {
        return $this->montant;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
