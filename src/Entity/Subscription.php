<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration_months = null;

    /**
     * @var Collection<int, UserSubscriptions>
     */
    #[ORM\OneToMany(targetEntity: UserSubscriptions::class, mappedBy: 'SubscriptionId')]
    private Collection $userSubscriptions;

    public function __construct()
    {
        $this->userSubscriptions = new ArrayCollection();
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDurationMonths(): ?int
    {
        return $this->duration_months;
    }

    public function setDurationMonths(?int $duration_months): static
    {
        $this->duration_months = $duration_months;

        return $this;
    }

    /**
     * @return Collection<int, UserSubscriptions>
     */
    public function getUserSubscriptions(): Collection
    {
        return $this->userSubscriptions;
    }

    public function addUserSubscription(UserSubscriptions $userSubscription): static
    {
        if (!$this->userSubscriptions->contains($userSubscription)) {
            $this->userSubscriptions->add($userSubscription);
            $userSubscription->setSubscriptionId($this);
        }

        return $this;
    }

    public function removeUserSubscription(UserSubscriptions $userSubscription): static
    {
        if ($this->userSubscriptions->removeElement($userSubscription)) {
            // set the owning side to null (unless already changed)
            if ($userSubscription->getSubscriptionId() === $this) {
                $userSubscription->setSubscriptionId(null);
            }
        }

        return $this;
    }
}
