<?php 
namespace App\Controller\Admin;

use App\Entity\UserSubscription;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Repository\UserSubscriptionRepository;
use App\Service\CsrfService;
use App\Service\UserSubscriptionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
#[Route('/admin/user-subscribe', name: 'admin_user_subscription_')]
class AdminUserSubscribeController extends AbstractController
{

    public function __construct(private UserSubscriptionManager $userSubscriptionManager)
    {
    }
    
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, UserSubscriptionRepository $userSubscriptionRepository): Response
    {
        $list = $this->userSubscriptionManager->listAll();
        return $this->json($list);
    }
    

    #[Route('/{userId}/{subscriptionId}/create', name: 'user_subscription_create', methods: ['POST'])]
    public function createUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserSubscriptionManager $manager
    ): JsonResponse {
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        $data = json_decode($request->getContent(), true) ?? [];
        $startDateStr = $data['start_date'] ?? '';
        $endDateStr = $data['end_date'] ?? '';

        if (!$startDateStr || !$endDateStr) {
            return new JsonResponse(['error' => 'Missing fields: start_date, end_date required'], 400);
        }

        try {
            $result = $manager->create($userId, $subscriptionId, $csrfToken, $startDateStr, $endDateStr);
            return new JsonResponse($result, 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

    }


    #[Route('/{userId}/{subscriptionId}/edit', name: 'user_subscription_edit', methods: ['PATCH'])]
    public function editUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserSubscriptionManager $manager
    ): JsonResponse {
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $manager->edit($userId, $subscriptionId, $data, $csrfToken);
            return $this->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{userId}/user/subscriptions', name: 'by_user', methods: ['GET'])]
    public function showByUser(
        int $userId,
        UserSubscriptionManager $manager
    ): JsonResponse {
        try {
            return $this->json($manager->showByUser($userId));
        } catch (HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

   #[Route('/{id}/show', name: 'user_subscription_show', methods: ['GET'])]
    public function show(
        int $id,
        UserSubscriptionManager $manager
    ): JsonResponse {
        try {
            return $this->json($manager->show($id));
        } catch (HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }


    #[Route('/{userSubscriptionId}/delete', name: 'user_subscription_delete', methods: ['DELETE'])]
    public function deleteUserSubscription(
        Request $request,
        int $userId,
        int $subscriptionId,
        UserSubscriptionManager $manager
    ): JsonResponse {
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        try {
            $manager->delete($userId, $subscriptionId, $csrfToken);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }}