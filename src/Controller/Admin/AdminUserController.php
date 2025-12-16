<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserEditType;
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

#[Route('/admin/user', name: 'admin_user_')]
class AdminUserController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $users = $userRepository->findAll();
        $data = array_map(fn(User $u) => $this->userManager->serializeUser($u), $users);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, User $user): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->userManager->serializeUser($user));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        CsrfService $csrfService
    ): Response {
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        // if ($this->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
        //     return $this->json(['error' => 'Forbidden'], 403);
        // }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $form = $this->createForm(UserEditType::class, $user, [
            'csrf_protection' => false,
        ]);
        $form->submit($data, false);

        if (!$form->isValid()) {
            return $this->json([
                'errors' => (string) $form->getErrors(true, false)
            ], 422);
        }

        if (!empty($data['password'])) {
            $user->setPasswordHash(
                $passwordHasher->hashPassword($user, $data['password'])
            );
        }

        $em->flush();

        return $this->json($this->userManager->serializeUser($user), 200);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

}
