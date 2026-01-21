<?php

namespace App\Controller\Admin;

use App\Entity\Diet;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DietManager;
use App\Service\CsrfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/diet', name: 'admin_diet_')]
class AdminDietController extends AbstractController
{
    public function __construct(
        private DietManager $dietManager,
        private EntityManagerInterface $entityManager,
        private CsrfService $csrfService,
    ) {
    }

    /**
     * GET all diets
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $diets = $this->dietManager->getAllDiets();

        return $this->json([
            'success' => true,
            'data' => $this->dietManager->serializeDiets($diets),
            'count' => count($diets),
        ]);
    }

    /**
     * GET all diets for a user
     */
    #[Route('/user/{userId}/diets', name: 'user_diets', methods: ['GET'])]
    public function getUserDiets(int $userId): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $diets = $user->getDiets()->toArray();

        return $this->json([
            'success' => true,
            'data' => $this->dietManager->serializeDiets($diets),
            'count' => count($diets),
        ]);
    }

    /**
     * GET a single diet by id
     */
    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $diet = $this->dietManager->getDietById($id);

        if (!$diet) {
            return $this->json([
                'success' => false,
                'error' => 'Diet not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->dietManager->serializeDiet($diet),
        ]);
    }

    /**
     * POST - Create a new diet
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            // Verify CSRF token
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid CSRF token',
                ], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);

            if (!isset($data['name']) || empty(trim($data['name']))) {
                return $this->json([
                    'success' => false,
                    'error' => 'Name field is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $diet = $this->dietManager->createDiet($data['name']);

            return $this->json([
                'success' => true,
                'message' => 'Diet created successfully',
                'data' => $this->dietManager->serializeDiet($diet),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error creating diet: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PUT - Update an existing diet
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            // Verify CSRF token
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid CSRF token',
                ], Response::HTTP_FORBIDDEN);
            }

            $diet = $this->dietManager->getDietById($id);

            if (!$diet) {
                return $this->json([
                    'success' => false,
                    'error' => 'Diet not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['name']) && !empty(trim($data['name']))) {
                $diet = $this->dietManager->updateDiet($diet, $data['name']);
            }

            return $this->json([
                'success' => true,
                'message' => 'Diet updated successfully',
                'data' => $this->dietManager->serializeDiet($diet),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error updating diet: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * DELETE - Delete a diet
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        try {
            // Verify CSRF token
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid CSRF token',
                ], Response::HTTP_FORBIDDEN);
            }

            $diet = $this->dietManager->getDietById($id);

            if (!$diet) {
                return $this->json([
                    'success' => false,
                    'error' => 'Diet not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->dietManager->deleteDiet($diet);

            return $this->json([
                'success' => true,
                'message' => 'Diet deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error deleting diet: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * POST - Associate a diet with a user
     */
    #[Route('/{id}/associate/{userId}', name: 'assign_to_user', methods: ['POST'])]
    public function assignToUser(int $id, int $userId, Request $request): JsonResponse
    {
        try {
            // Verify CSRF token
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid CSRF token',
                ], Response::HTTP_FORBIDDEN);
            }

            $diet = $this->dietManager->getDietById($id);
            if (!$diet) {
                return $this->json([
                    'success' => false,
                    'error' => 'Diet not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $user = $this->entityManager
                ->getRepository(User::class)
                ->find($userId);

            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'User not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if user already has this diet
            if ($diet->getUserHasDiet()->contains($user)) {
                return $this->json([
                    'success' => false,
                    'error' => 'User already has this diet',
                ], Response::HTTP_BAD_REQUEST);
            }

            $diet->addUserHasDiet($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Diet assigned to user successfully',
                'data' => $this->dietManager->serializeDiet($diet),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error assigning diet to user: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * DELETE - Remove a diet from a user
     */
    #[Route('/{id}/associate/{userId}/delete', name: 'remove_from_user', methods: ['DELETE'])]
    public function removeFromUser(int $id, int $userId, Request $request): JsonResponse
    {
        try {
            // Verify CSRF token
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid CSRF token',
                ], Response::HTTP_FORBIDDEN);
            }

            $diet = $this->dietManager->getDietById($id);
            if (!$diet) {
                return $this->json([
                    'success' => false,
                    'error' => 'Diet not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $user = $this->entityManager
                ->getRepository(User::class)
                ->find($userId);

            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'User not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if user has this diet
            if (!$diet->getUserHasDiet()->contains($user)) {
                return $this->json([
                    'success' => false,
                    'error' => 'User does not have this diet',
                ], Response::HTTP_BAD_REQUEST);
            }

            $diet->removeUserHasDiet($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Diet removed from user successfully',
                'data' => $this->dietManager->serializeDiet($diet),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error removing diet from user: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Helper method to serialize a diet entity
     */
    private function serializeDiet(Diet $diet): array
    {
        return [
            'id' => $diet->getId(),
            'name' => $diet->getName(),
        ];
    }
}