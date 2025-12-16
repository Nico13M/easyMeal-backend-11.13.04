<?php 
namespace App\Controller\Admin;

use App\Entity\UserSubscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Repository\UserSubscriptionRepository;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
#[Route('/admin/user-subscribe', name: 'admin_user_subscription_')]
class AdminUserSubscribeController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
   public function index(Request $request, EntityManagerInterface $em, UserSubscriptionRepository $userSubscriptionRepository): Response
    {
        $userSubscriptions = $userSubscriptionRepository->findAll();

        $subscriptionList = [];

       foreach ($userSubscriptions as $key => $userSubscription) {
            $subscriptionList[$key] = [
                'id' => $userSubscription->getId(),
                'UserId' => $userSubscription->getUserId()->getId(),
                'SubscriptionId' => $userSubscription->getSubscriptionId()->getId(),
                'is_active' => $userSubscription->isActive(),
                'start_date' => $userSubscription->getStartDate()->format(DATE_ATOM),
                'end_date' => $userSubscription->getEndDate()->format(DATE_ATOM),
            ];
        }
       return $this->json($subscriptionList);
    }

    public function serializeUserSubscription(UserSubscription $userSubscription): array
    {
        $subscription = $userSubscription->getSubscriptionId();
        return [
            'id' => $userSubscription->getId(),
            'start_date' => $userSubscription->getStartDate()->format(DATE_ATOM),
            'end_date' => $userSubscription->getEndDate()->format(DATE_ATOM),
            'is_active' => $userSubscription->IsActive(),
            'subscription' => [
                'id' => $subscription->getId(),
                'name' => $subscription->getName(),
                'price' => $subscription->getPrice(),
                'duration_months' => $subscription->getDurationMonths(),
            ],
        ];
    }

    #[Route('/{userId}/{subscriptionId}/create', name: 'user_subscription_create', methods: ['POST'])]
    public function createUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
    
        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $subscription = $subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $startDateStr = $data['start_date'] ?? '';
        $endDateStr = $data['end_date'] ?? '';

        if (!$startDateStr || !$endDateStr) {
            return new JsonResponse(['error' => 'Missing fields: start_date, end_date required'], 400);
        }

        try {
            $startDate = new \DateTime($startDateStr);
            $endDate = new \DateTime($endDateStr);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }

        $userSubscription = new UserSubscription();
        $userSubscription->setUserId($user);
        $userSubscription->setSubscriptionId($subscription);
        $userSubscription->setStartDate($startDate);
        $userSubscription->setEndDate($endDate);
        $userSubscription->setIsActive(true);

        $em->persist($userSubscription);
        $em->flush();

        return new JsonResponse(['message' => 'User subscription created', 'id' => $userSubscription->getId()], 201);

    } 


    #[Route('/{userId}/{subscriptionId}/edit', name: 'user_subscription_edit', methods: ['PATCH'])]
    public function editUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
        
        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $subscription = $subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $userSubscription = $em->getRepository(UserSubscription::class)
            ->findOneBy(['UserId' => $user, 'SubscriptionId' => $subscription]);

        if (!$userSubscription) {
            return $this->json(['error' => 'User subscription not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['start_date'])) {
            try {
                $startDate = new \DateTime($data['start_date']);
                $userSubscription->setStartDate($startDate);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Invalid start_date format'], 400);
            }
        }

        if (isset($data['end_date'])) {
            try {
                $endDate = new \DateTime($data['end_date']);
                $userSubscription->setEndDate($endDate);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Invalid end_date format'], 400);
            }
        }

        if (isset($data['is_active'])) {
            $userSubscription->setIsActive((bool)$data['is_active']);
        }

        $em->persist($userSubscription);
        $em->flush();

        return $this->json($this->serializeUserSubscription($userSubscription));
    }

    #[Route('/{userId}/{subscriptionId}/show', name: 'user_subscription_show', methods: ['GET'])]
    public function showUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        
        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $subscription = $subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        $userSubscription = $em->getRepository(UserSubscription::class)
            ->findOneBy(['UserId' => $user, 'SubscriptionId' => $subscription]);

        if (!$userSubscription) {
            return $this->json(['error' => 'User subscription not found'], 404);
        }

        return $this->json($this->serializeUserSubscription($userSubscription));
    }

    #[Route('/{userId}/{subscriptionId}/delete', name: 'user_subscription_delete', methods: ['DELETE'])]
public function deleteUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
        
        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $subscription = $subscriptionRepository->find($subscriptionId);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $userSubscription = $em->getRepository(UserSubscription::class)
            ->findOneBy(['UserId' => $user, 'SubscriptionId' => $subscription]);

        if (!$userSubscription) {
            return $this->json(['error' => 'User subscription not found'], 404);
        }

        $em->remove($userSubscription);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }}