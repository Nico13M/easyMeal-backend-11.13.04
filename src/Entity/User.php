<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements PasswordAuthenticatedUserInterface, UserInterface
{
 
       use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private ?string $firstname = null;

    #[ORM\Column(length: 45)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password_hash = null;

    /**
     * @var Collection<int, UserSubscriptions>
     */
    #[ORM\OneToMany(targetEntity: UserSubscription::class, mappedBy: 'UserId')]
    private Collection $userSubscriptions;

    /**
     * @var Collection<int, Diet>
     */
    #[ORM\ManyToMany(targetEntity: Diet::class, mappedBy: 'user_has_diet')]
    private Collection $diets;

    /**
     * @var Collection<int, Ingredient>
     */
    #[ORM\ManyToMany(targetEntity: Ingredient::class, inversedBy: 'users')]
    private Collection $user_ingredients_blacklist;

    /**
     * @var Collection<int, Recipe>
     */
    #[ORM\ManyToMany(targetEntity: Recipe::class, inversedBy: 'user_recipe_preferences')]
    private Collection $user_recipe_preferences;

    #[ORM\OneToOne(mappedBy: 'user_frigo', cascade: ['persist', 'remove'])]
    private ?Frigo $frigo = null;

    public function __construct()
    {
        $this->userSubscriptions = new ArrayCollection();
        $this->diets = new ArrayCollection();
        $this->user_ingredients_blacklist = new ArrayCollection();
        $this->user_recipe_preferences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(string $password_hash): static
    {
        $this->password_hash = $password_hash;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        // return a default role; you can adapt to store roles in DB later
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary sensitive data on the user, clear it here
    }

    /**
     * @return Collection<int, UserSubscriptions>
     */
    public function getUserSubscriptions(): Collection
    {
        return $this->userSubscriptions;
    }

    public function addUserSubscription(UserSubscription $userSubscription): static
    {
        if (!$this->userSubscriptions->contains($userSubscription)) {
            $this->userSubscriptions->add($userSubscription);
            $userSubscription->setUserId($this);
        }

        return $this;
    }

    public function removeUserSubscription(UserSubscription $userSubscription): static
    {
        if ($this->userSubscriptions->removeElement($userSubscription)) {
            // set the owning side to null (unless already changed)
            if ($userSubscription->getUserId() === $this) {
                $userSubscription->setUserId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Diet>
     */
    public function getDiets(): Collection
    {
        return $this->diets;
    }

    public function addDiet(Diet $diet): static
    {
        if (!$this->diets->contains($diet)) {
            $this->diets->add($diet);
            $diet->addUserHasDiet($this);
        }

        return $this;
    }

    public function removeDiet(Diet $diet): static
    {
        if ($this->diets->removeElement($diet)) {
            $diet->removeUserHasDiet($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Ingredient>
     */
    public function getUserIngredientsBlacklist(): Collection
    {
        return $this->user_ingredients_blacklist;
    }

    public function addUserIngredientsBlacklist(Ingredient $userIngredientsBlacklist): static
    {
        if (!$this->user_ingredients_blacklist->contains($userIngredientsBlacklist)) {
            $this->user_ingredients_blacklist->add($userIngredientsBlacklist);
        }

        return $this;
    }

    public function removeUserIngredientsBlacklist(Ingredient $userIngredientsBlacklist): static
    {
        $this->user_ingredients_blacklist->removeElement($userIngredientsBlacklist);

        return $this;
    }

    /**
     * @return Collection<int, Recipe>
     */
    public function getUserRecipePreferences(): Collection
    {
        return $this->user_recipe_preferences;
    }

    public function addUserRecipePreference(Recipe $user_recipe_preference): static
    {
        if (!$this->user_recipe_preferences->contains($user_recipe_preference)) {
            $this->user_recipe_preferences->add($user_recipe_preference);
        }

        return $this;
    }

    public function removeUserRecipePreference(Recipe $user_recipe_preference): static
    {
        $this->user_recipe_preferences->removeElement($user_recipe_preference);

        return $this;
    }

    public function getFrigo(): ?Frigo
    {
        return $this->frigo;
    }

    public function setFrigo(?Frigo $frigo): static
    {
        // unset the owning side of the relation if necessary
        if ($frigo === null && $this->frigo !== null) {
            $this->frigo->setUserFrigo(null);
        }

        // set the owning side of the relation if necessary
        if ($frigo !== null && $frigo->getUserFrigo() !== $this) {
            $frigo->setUserFrigo($this);
        }

        $this->frigo = $frigo;

        return $this;
    }
}
