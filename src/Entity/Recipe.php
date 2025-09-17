<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $ingredients = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $instructions = null;

    #[ORM\Column]
    private ?int $preparationTime = null;

    #[ORM\Column]
    private ?int $cookingTime = null;

    #[ORM\Column]
    private ?int $servings = null;

    #[ORM\Column(length: 50)]
    private ?string $difficulty = null;

    #[ORM\Column(length: 100)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    /**
     * @var Collection<int, RecipeRating>
     */
    #[ORM\OneToMany(targetEntity: RecipeRating::class, mappedBy: 'recipe', orphanRemoval: true)]
    private Collection $ratings;

    /**
     * @var Collection<int, RecipeComment>
     */
    #[ORM\OneToMany(targetEntity: RecipeComment::class, mappedBy: 'recipe', orphanRemoval: true)]
    private Collection $comments;

    #[ORM\Column]
    private ?int $views = 0;

    #[ORM\Column]
    private ?bool $isPublic = true;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;


    public function __construct()
    {
        $this->ratings = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(string $ingredients): static
    {
        $this->ingredients = $ingredients;
        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(string $instructions): static
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function getPreparationTime(): ?int
    {
        return $this->preparationTime;
    }

    public function setPreparationTime(int $preparationTime): static
    {
        $this->preparationTime = $preparationTime;
        return $this;
    }

    public function getCookingTime(): ?int
    {
        return $this->cookingTime;
    }

    public function setCookingTime(int $cookingTime): static
    {
        $this->cookingTime = $cookingTime;
        return $this;
    }

    public function getTotalTime(): int
    {
        return $this->preparationTime + $this->cookingTime;
    }

    public function getServings(): ?int
    {
        return $this->servings;
    }

    public function setServings(int $servings): static
    {
        $this->servings = $servings;
        return $this;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return Collection<int, RecipeRating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function addRating(RecipeRating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setRecipe($this);
        }
        return $this;
    }

    public function removeRating(RecipeRating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            if ($rating->getRecipe() === $this) {
                $rating->setRecipe(null);
            }
        }
        return $this;
    }

    public function getAverageRating(): float
    {
        $approved = $this->reviews->filter(fn(Review $r) => $r->isApproved());
        $count = $approved->count();
        if ($count === 0) return 0.0;
        $sum = 0;
        foreach ($approved as $r) { $sum += (int) $r->getRating(); }
        return round($sum / $count, 1);
    }

    /**
     * @return Collection<int, RecipeComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(RecipeComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setRecipe($this);
        }
        return $this;
    }

    public function removeComment(RecipeComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getRecipe() === $this) {
                $comment->setRecipe(null);
            }
        }
        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function incrementViews(): static
    {
        $this->views++;
        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getDifficultyIcon(): string
    {
        return match($this->difficulty) {
            'facile' => '⭐',
            'moyen' => '⭐⭐',
            'difficile' => '⭐⭐⭐',
            default => '⭐'
        };
    }

    public function getCategoryIcon(): string
    {
        return match($this->category) {
            'dessert' => '🍰',
            'boisson' => '🥤',
            'plat' => '🍽️',
            'sauce' => '🥄',
            'confiture' => '🍯',
            'salade' => '🥗',
            default => '🍊'
        };
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setRecipe($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getRecipe() === $this) {
                $review->setRecipe(null);
            }
        }

        return $this;
    }
}
