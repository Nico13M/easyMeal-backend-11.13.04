<?php

namespace App\Entity;

use App\Repository\FrigoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FrigoRepository::class)]
class Frigo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'frigo', cascade: ['persist', 'remove'])]
    private ?User $user_frigo = null;

    /**
     * @var Collection<int, Ingredient>
     */
    #[ORM\ManyToMany(targetEntity: Ingredient::class, inversedBy: 'frigos')]
    private Collection $ingredients_has_frigo;

    public function __construct()
    {
        $this->ingredients_has_frigo = new ArrayCollection();
    }

   
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserFrigo(): ?User
    {
        return $this->user_frigo;
    }

    public function setUserFrigo(?User $user_frigo): static
    {
        $this->user_frigo = $user_frigo;

        return $this;
    }

    /**
     * @return Collection<int, Ingredient>
     */
    public function getIngredientsHasFrigo(): Collection
    {
        return $this->ingredients_has_frigo;
    }

    public function addIngredientsHasFrigo(Ingredient $ingredientsHasFrigo): static
    {
        if (!$this->ingredients_has_frigo->contains($ingredientsHasFrigo)) {
            $this->ingredients_has_frigo->add($ingredientsHasFrigo);
        }

        return $this;
    }

    public function removeIngredientsHasFrigo(Ingredient $ingredientsHasFrigo): static
    {
        $this->ingredients_has_frigo->removeElement($ingredientsHasFrigo);

        return $this;
    }

}
