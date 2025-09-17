<?php

namespace App\Entity;

use App\Repository\OrangeInventoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrangeInventoryRepository::class)]
class OrangeInventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inventories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $variety = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $expirationDate = null;

    #[ORM\Column(name: 'storage_condition', length: 50, nullable: true)]
    private ?string $condition = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->condition = 'excellent';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getVariety(): ?string
    {
        return $this->variety;
    }

    public function setVariety(string $variety): static
    {
        $this->variety = $variety;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(\DateTimeInterface $purchaseDate): static
    {
        $this->purchaseDate = $purchaseDate;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(\DateTimeInterface $expirationDate): static
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): self { $this->condition = $condition; return $this; }


    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getDaysUntilExpiration(): int
    {
        $now = new \DateTime();
        $interval = $now->diff($this->expirationDate);
        return $interval->invert ? -$interval->days : $interval->days;
    }

    public function isExpiringSoon(): bool
    {
        return $this->getDaysUntilExpiration() <= 3;
    }

    public function isExpired(): bool
    {
        return $this->getDaysUntilExpiration() < 0;
    }

    public function getConditionIcon(): string
    {
        return match($this->condition) {
            'excellent' => '🟢',
            'good' => '🟡',
            'fair' => '🟠',
            'poor' => '🔴',
            default => '⚪'
        };
    }

    public function getVarietyIcon(): string
    {
        return match(strtolower($this->variety)) {
            'navel' => '🍊',
            'valencia' => '🟠',
            'sanguine', 'blood' => '🔴',
            'mandarine' => '🟡',
            'bergamote' => '🟢',
            default => '🍊'
        };
    }

    public function getStatusColor(): string
    {
        if ($this->isExpired()) {
            return 'danger';
        } elseif ($this->isExpiringSoon()) {
            return 'warning';
        } else {
            return 'success';
        }
    }
}
