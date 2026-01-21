<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[Route('/admin/auth', name: 'admin_auth_')]
class AdminAuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $firstname = $data['firstname'] ?? '';
        $lastname = $data['lastname'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$firstname || !$lastname || !$email || !$password) {
            return new JsonResponse(['error' => 'Missing fields: firstname, lastname, email, password required'], 400);
        }

        if (strlen($password) < 6) {
            return new JsonResponse(['error' => 'Password too short (min 6 characters)'], 400);
        }

        $repo = $em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);
        if ($existing) {
            return new JsonResponse(['error' => 'Email already used'], 400);
        }

        $user = new User();
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setEmail($email);

        $hash = $passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hash);

        if (method_exists($user, 'setCreatedAt')) {
            $user->setCreatedAt(new \DateTime());
        }
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTime());
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User registered'], 201);
    }
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        try {
            $user = $userProvider->loadUserByIdentifier($email);
            if (!$user instanceof \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface) {
                throw new AuthenticationException('User cannot be authenticated with a password.');
            }
            if (!$passwordHasher->isPasswordValid($user, $password)) {
                throw new AuthenticationException('Invalid credentials');
            }
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);

            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event);

            return new JsonResponse(['message' => 'Login successful']);
        } catch (AuthenticationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        }
    }
}
