<?php

namespace App\Entity;

use App\Repository\FavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'favorite')]
#[ORM\UniqueConstraint(name: 'uniq_fav_user_recipe', columns: ['user_id', 'recipe_id'])]
class Favorite
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    // Un favori appartient à 1 utilisateur
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\User $user = null;

    // …et à 1 recette
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\Recipe $recipe = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?\App\Entity\User { return $this->user; }
    public function setUser(?\App\Entity\User $user): self { $this->user = $user; return $this; }

    public function getRecipe(): ?\App\Entity\Recipe { return $this->recipe; }
    public function setRecipe(?\App\Entity\Recipe $recipe): self { $this->recipe = $recipe; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): self { $this->createdAt = $d; return $this; }
}
