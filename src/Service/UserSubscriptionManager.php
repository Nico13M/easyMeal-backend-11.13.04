<?php
namespace App\Service;

use App\Entity\UserSubscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Repository\UserSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserSubscriptionManager
{
    private UserRepository $userRepository;
    private SubscriptionRepository $subscriptionRepository;
    private UserSubscriptionRepository $userSubscriptionRepository;
    private EntityManagerInterface $em;
    private CsrfService $csrfService;

    public function __construct(
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        UserSubscriptionRepository $userSubscriptionRepository,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ) {
        $this->userRepository = $userRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->userSubscriptionRepository = $userSubscriptionRepository;
        $this->em = $em;
        $this->csrfService = $csrfService;
    }

    public function listAll(): array
    {
        $userSubscriptions = $this->userSubscriptionRepository->findAll();
        $list = [];
        foreach ($userSubscriptions as $key => $us) {
            /** @var UserSubscription $us */
            $list[$key] = $this->serialize($us);
        }
        return $list;
    }

    public function serialize(UserSubscription $userSubscription): array
    {
        $subscription = $userSubscription->getSubscriptionId();
        return [
            'id' => $userSubscription->getId(),
            'start_date' => $userSubscription->getStartDate()->format(DATE_ATOM),
            'end_date' => $userSubscription->getEndDate()->format(DATE_ATOM),
            'is_active' => $userSubscription->isActive(),
            'subscription' => [
                'id' => $subscription->getId(),
                'name' => $subscription->getName(),
                'price' => $subscription->getPrice(),
                'duration_months' => $subscription->getDurationMonths(),
            ],
            'UserId' => $userSubscription->getUserId()->getId(),
            'SubscriptionId' => $subscription->getId(),
        ];
    }

    public function create(int $userId, int $subscriptionId, string $csrfToken, string $startDateStr, string $endDateStr): array
    {
        if (!$this->csrfService->isValid('api', $csrfToken)) {
            throw new HttpException(403, 'Invalid CSRF token');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            throw new HttpException(404, 'Subscription not found');
        }

        try {
            $startDate = new \DateTime($startDateStr);
            $endDate = new \DateTime($endDateStr);
        } catch (\Exception $e) {
            throw new HttpException(400, 'Invalid date format');
        }

        $userSubscription = new UserSubscription();
        $userSubscription->setUserId($user);
        $userSubscription->setSubscriptionId($subscription);
        $userSubscription->setStartDate($startDate);
        $userSubscription->setEndDate($endDate);
        $userSubscription->setIsActive(true);

        $this->em->persist($userSubscription);
        $this->em->flush();

        return ['message' => 'User subscription created', 'id' => $userSubscription->getId()];
    }

    public function edit(int $userId, int $subscriptionId, array $data, string $csrfToken): array
    {
        if (!$this->csrfService->isValid('api', $csrfToken)) {
            throw new HttpException(403, 'Invalid CSRF token');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            throw new HttpException(404, 'Subscription not found');
        }

        $userSubscription = $this->userSubscriptionRepository->findOneBy(['UserId' => $user, 'SubscriptionId' => $subscription]);
        if (!$userSubscription) {
            throw new HttpException(404, 'User subscription not found');
        }

        if (isset($data['start_date'])) {
            try {
                $userSubscription->setStartDate(new \DateTime($data['start_date']));
            } catch (\Exception $e) {
                throw new HttpException(400, 'Invalid start_date format');
            }
        }

        if (isset($data['end_date'])) {
            try {
                $userSubscription->setEndDate(new \DateTime($data['end_date']));
            } catch (\Exception $e) {
                throw new HttpException(400, 'Invalid end_date format');
            }
        }

        if (isset($data['is_active'])) {
            $userSubscription->setIsActive((bool)$data['is_active']);
        }

        $this->em->persist($userSubscription);
        $this->em->flush();

        /** @var \App\Entity\UserSubscription $userSubscription */
        if (!($userSubscription instanceof UserSubscription)) {
            throw new HttpException(500, 'Unexpected type for userSubscription');
        }

        return $this->serialize($userSubscription);
    }

   public function showByUser(int $userId): array
{
    $user = $this->userRepository->find($userId);
    if (!$user) {
        throw new HttpException(404, 'User not found');
    }

    $subscriptions = $this->userSubscriptionRepository
        ->findBy(['UserId' => $user]);

    return array_map(
        fn (UserSubscription $us) => $this->serialize($us),
        $subscriptions
    );
}

        
    public function show(int $userSubscriptionId): array
{
    $userSubscription = $this->userSubscriptionRepository->find($userSubscriptionId);

    if (!$userSubscription) {
        throw new HttpException(404, 'User subscription not found');
    }

    return $this->serialize($userSubscription);
}


    public function delete(int $userId, int $subscriptionId, string $csrfToken): void
    {
        if (!$this->csrfService->isValid('api', $csrfToken)) {
            throw new HttpException(403, 'Invalid CSRF token');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            throw new HttpException(404, 'Subscription not found');
        }

        $userSubscription = $this->userSubscriptionRepository->findOneBy(['UserId' => $user, 'SubscriptionId' => $subscription]);
        if (!$userSubscription) {
            throw new HttpException(404, 'User subscription not found');
        }

        $this->em->remove($userSubscription);
        $this->em->flush();
    }
}
