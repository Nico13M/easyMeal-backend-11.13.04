<?php

namespace App\Entity;

use App\Repository\IngredientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
// Import indispensable pour le Slug automatique
use Gedmo\Mapping\Annotation as Gedmo;
// Import indispensable pour les dates (created_at, updated_at)
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
class Ingredient
{
    // Ajoute les dates automatiques (created_at, updated_at)
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])] // Génère le slug automatiquement basé sur le nom
    private ?string $slug = null;

    /**
     * @var Collection<int, RecipeIngredient>
     */
    #[ORM\OneToMany(targetEntity: RecipeIngredient::class, mappedBy: 'ingredient')]
    private Collection $recipeIngredients;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'user_ingredients_blacklist')]
    private Collection $users;

    /**
     * @var Collection<int, Frigo>
     */
    #[ORM\ManyToMany(targetEntity: Frigo::class, mappedBy: 'ingredients_has_frigo')]
    private Collection $frigos;

    public function __construct()
    {
        $this->recipeIngredients = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->frigos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @return Collection<int, RecipeIngredient>
     */
    public function getRecipeIngredients(): Collection
    {
        return $this->recipeIngredients;
    }

    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients->add($recipeIngredient);
            $recipeIngredient->setIngredient($this);
        }

        return $this;
    }

    public function removeRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if ($this->recipeIngredients->removeElement($recipeIngredient)) {
            // set the owning side to null (unless already changed)
            if ($recipeIngredient->getIngredient() === $this) {
                $recipeIngredient->setIngredient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addUserIngredientsBlacklist($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            $user->removeUserIngredientsBlacklist($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Frigo>
     */
    public function getFrigos(): Collection
    {
        return $this->frigos;
    }

    public function addFrigo(Frigo $frigo): static
    {
        if (!$this->frigos->contains($frigo)) {
            $this->frigos->add($frigo);
            $frigo->addIngredientsHasFrigo($this);
        }

        return $this;
    }

    public function removeFrigo(Frigo $frigo): static
    {
        if ($this->frigos->removeElement($frigo)) {
            $frigo->removeIngredientsHasFrigo($this);
        }

        return $this;
    }
}