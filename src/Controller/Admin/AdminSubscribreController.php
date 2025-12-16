<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserEditType;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/subscribre', name: 'admin_subscribre_')]
class AdminSubscribreController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, SubscriptionRepository $subscriptionRepository): Response
    {
        $subcriptionList = $subscriptionRepository->findAll();

        foreach ($subcriptionList as $key => $subscription) {
            $subcriptionList[$key] = [
                'id' => $subscription->getId(),
                'name' => $subscription->getName(),
                'price' => $subscription->getPrice(),
                'duration_months' => $subscription->getDurationMonths(),
            ];
        }
       return $this->json($subcriptionList);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, int $id, SubscriptionRepository $subscriptionRepository): Response
    {
        $subscription = $subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }
        return $this->json($subscription);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])] 
public function edit(
        Request $request,
        int $id,
        SubscriptionRepository $subscriptionRepository,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        $subscription = $subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $subscription->setName($data['name']);
        }
        if (isset($data['price'])) {
            $subscription->setPrice($data['price']);
        }
        if (isset($data['duration_months'])) {
            $subscription->setDurationMonths($data['duration_months']);
        }

        $em->persist($subscription);
        $em->flush();

        return $this->json($subscription);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, CsrfService $csrfService): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $price = (int)$data['price'] ?? 0;
        $durationMonths = (int)$data['duration_months'] ?? 0;

        if (!$name || !$price || !$durationMonths) {
            return new JsonResponse(['error' => 'Missing fields: name, price, duration_months required'], 400);
        }

         // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $subscription = new \App\Entity\Subscription();
        $subscription->setName($name);
        $subscription->setPrice($price);
        $subscription->setDurationMonths($durationMonths);

        $em->persist($subscription);
        $em->flush();

        return new JsonResponse(['message' => 'Subscription created', 'id' => $subscription->getId()], 201);
    }

}
