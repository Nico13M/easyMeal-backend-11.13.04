<?php

namespace App\Controller\Admin;

use App\Service\CsrfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/security', name: 'api_security_')]
class SecurityController extends AbstractController
{
    #[Route('/csrf-token', name: 'csrf_token', methods: ['GET'])]
    public function csrf(CsrfService $csrfService): JsonResponse
    {
        return $this->json([
            'csrfToken' => $csrfService->generate('api'),
        ]);
    }
}
