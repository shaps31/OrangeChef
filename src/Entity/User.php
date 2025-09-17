<?php

namespace App\Entity;

use AllowDynamicProperties;
use App\Entity\Recipe;
use App\Entity\OrangeInventory;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[AllowDynamicProperties]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L’email est obligatoire.')]
    #[Assert\Email(message: 'Email invalide.')]
    private ?string $email = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /** @var string|null The hashed password */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;



    /** @var Collection<int, OrangeInventory> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OrangeInventory::class, orphanRemoval: true)]
    private Collection $inventories;

    /** @var Collection<int, Recipe> */
    #[ORM\OneToMany(targetEntity: Recipe::class, mappedBy: 'author')]
    private Collection $recipes;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationToken = null;

    public function __construct()
    {
        $this->oranges = new ArrayCollection();
        $this->inventories = new ArrayCollection();
        $this->recipes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self
    {
        $this->email = $email ? mb_strtolower($email) : null;
        return $this;
    }


    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    /** @param list<string> $roles */
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);
        return $data;
    }

    #[\Deprecated] public function eraseCredentials(): void {}

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(?string $token): static { $this->verificationToken = $token; return $this; }






    /** @return Collection<int, OrangeInventory> */
    public function getInventories(): Collection { return $this->inventories; }

    public function addInventory(OrangeInventory $inv): static
    {
        if (!$this->inventories->contains($inv)) {
            $this->inventories->add($inv);
            $inv->setUser($this);
        }
        return $this;
    }

    public function removeInventory(OrangeInventory $inv): static
    {
        if ($this->inventories->removeElement($inv) && $inv->getUser() === $this) {
            $inv->setUser(null);
        }
        return $this;
    }

    /** @return Collection<int, Recipe> */
    public function getRecipes(): Collection { return $this->recipes; }

    public function addRecipe(Recipe $recipe): static
    {
        if (!$this->recipes->contains($recipe)) {
            $this->recipes->add($recipe);
            $recipe->setAuthor($this);
        }
        return $this;
    }

    public function removeRecipe(Recipe $recipe): static
    {
        if ($this->recipes->removeElement($recipe) && $recipe->getAuthor() === $this) {
            $recipe->setAuthor(null);
        }
        return $this;
    }

    public function getRecipeCount(): int { return $this->recipes->count(); }

    public function getDisplayName(): string
    {
        if ($this->prenom && $this->nom) return $this->prenom.' '.$this->nom;
        return $this->prenom ?? (string) $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->prenom;
    }
}
