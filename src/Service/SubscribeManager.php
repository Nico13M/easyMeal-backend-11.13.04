<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscribeManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionRepository $subscriptionRepository
    ) {
    }

    /**
     * Retourne les subscriptions sous forme de tableau simple
     *
     * @return array<int, array>
     */
    public function listAll(): array
    {
        $list = $this->subscriptionRepository->findAll();
        $result = [];
        foreach ($list as $subscription) {
            $result[] = [
                'id' => $subscription->getId(),
                'name' => $subscription->getName(),
                'price' => $subscription->getPrice(),
                'duration_months' => $subscription->getDurationMonths(),
            ];
        }

        return $result;
    }

    public function find(int $id): ?Subscription
    {
        return $this->subscriptionRepository->find($id);
    }

    public function create(string $name, int $price, int $durationMonths): Subscription
    {
        $subscription = new Subscription();
        $subscription->setName($name);
        $subscription->setPrice($price);
        $subscription->setDurationMonths($durationMonths);

        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        if (isset($data['name'])) {
            $subscription->setName($data['name']);
        }
        if (isset($data['price'])) {
            $subscription->setPrice($data['price']);
        }
        if (isset($data['duration_months'])) {
            $subscription->setDurationMonths($data['duration_months']);
        }

        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }

    public function delete(Subscription $subscription): void
    {
        $this->em->remove($subscription);
        $this->em->flush();
    }
}
