<?php

namespace App\Entity;

use App\Repository\DietRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DietRepository::class)]
class Diet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private ?string $name = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'diets')]
    private Collection $user_has_diet;

    public function __construct()
    {
        $this->user_has_diet = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */
    public function getUserHasDiet(): Collection
    {
        return $this->user_has_diet;
    }

    public function addUserHasDiet(User $userHasDiet): static
    {
        if (!$this->user_has_diet->contains($userHasDiet)) {
            $this->user_has_diet->add($userHasDiet);
        }

        return $this;
    }

    public function removeUserHasDiet(User $userHasDiet): static
    {
        $this->user_has_diet->removeElement($userHasDiet);

        return $this;
    }
}
