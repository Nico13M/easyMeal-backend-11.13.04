<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class DataService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $externalApiUrl = 'https://api.external-service.com/data' // URL configurable
    ) {}

    /**
     * Envoie les données utilisateur à l'endpoint externe
     */
    public function sendUserData(array $userData, string $userId): array
    {
        try {
            $payload = [
                'user_id' => $userId,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                'data' => $userData
            ];

            $response = $this->httpClient->request('POST', $this->externalApiUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->getApiToken(), // Token configurable
                ],
                'timeout' => 10
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Données utilisateur envoyées avec succès', [
                    'user_id' => $userId,
                    'status_code' => $statusCode
                ]);

                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'data' => $responseData
                ];
            } else {
                $this->logger->error('Erreur lors de l\'envoi des données utilisateur', [
                    'user_id' => $userId,
                    'status_code' => $statusCode,
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'error' => $responseData
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception lors de l\'envoi des données utilisateur', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère le token API depuis les variables d'environnement
     */
    private function getApiToken(): string
    {
        return $_ENV['EXTERNAL_API_TOKEN'] ?? 'default-token';
    }

    /**
     * Configure l'URL de l'API externe (pour les tests)
     */
    public function setApiUrl(string $url): void
    {
        $this->externalApiUrl = $url;
    }

    /**
     * Envoie les données de recherche de recettes à l'endpoint externe
     */
    public function sendSearchData(array $searchData, string $userId): array
    {
        try {
            $payload = [
                'user_id' => $userId,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                'search_criteria' => $searchData
            ];

            $response = $this->httpClient->request('POST', $this->externalApiUrl . '/search-recipes', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->getApiToken(),
                ],
                'timeout' => 15 // Timeout plus long pour la recherche
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Recherche de recettes effectuée avec succès', [
                    'user_id' => $userId,
                    'status_code' => $statusCode,
                    'results_count' => count($responseData['recipes'] ?? [])
                ]);

                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'data' => $responseData
                ];
            } else {
                $this->logger->error('Erreur lors de la recherche de recettes', [
                    'user_id' => $userId,
                    'status_code' => $statusCode,
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'error' => $responseData
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception lors de la recherche de recettes', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}