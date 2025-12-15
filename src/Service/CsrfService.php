<?php

namespace App\Service;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfService
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function generate(string $tokenId): string
    {
        return $this->csrfTokenManager->getToken($tokenId)->getValue();
    }

    public function isValid(string $tokenId, ?string $token): bool
    {
        if (!$token) {
            return false;
        }

        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, $token)
        );
    }
}
