<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\IngredientRepository;
use Symfony\Component\HttpFoundation\Request;

class RecipeSearchService
{
    public function __construct(
        private DataService $dataService,
        private IngredientRepository $ingredientRepository
    ) {}

    /**
     * Effectue une recherche de recettes basée sur les critères utilisateur
     */
    public function searchRecipes(User $user, Request $request): array
    {
        // Récupérer les données de base de l'utilisateur
        $blacklistIds = $this->getUserBlacklistIds($user);
        $dietIds = $this->getUserDietIds($user);

        // Traiter les ingrédients
        $ingredientsData = $this->processIngredients($user, $request);
        $frigoIngredients = $ingredientsData['frigo'];
        $formIngredients = $ingredientsData['form'];
        $allIngredients = array_merge($frigoIngredients, $formIngredients);
        $servings = (int) $request->query->get('servings', 4);
        // Préparer les données pour l'API externe
        $searchData = [
            'blacklist_ids' => $blacklistIds,
            'diet_ids' => $dietIds,
            'frigo_ingredients' => $frigoIngredients,
            'form_ingredients' => $formIngredients,
            'all_ingredients' => $allIngredients,
            'servings' => $servings,
        ];

        // Envoyer les données à l'API externe
        $result = $this->dataService->sendSearchData($searchData, $user->getId());

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Erreur inconnue'
            ];
        }

        return [
            'success' => true,
            'recipes' => $result['data']['recipes'] ?? [],
            'search_criteria' => $searchData,
            'total_results' => count($result['data']['recipes'] ?? [])
        ];
    }

    /**
     * Récupère les IDs des ingrédients blacklistés par l'utilisateur
     */
    private function getUserBlacklistIds(User $user): array
    {
        return $user->getUserIngredientsBlacklist()
            ->map(fn($ingredient) => $ingredient->getId())
            ->toArray();
    }

    /**
     * Récupère les IDs des régimes de l'utilisateur
     */
    private function getUserDietIds(User $user): array
    {
        return $user->getDiets()
            ->map(fn($diet) => $diet->getId())
            ->toArray();
    }

    /**
     * Traite les ingrédients du frigo et du formulaire
     */
    private function processIngredients(User $user, Request $request): array
    {
        $frigo = $request->query->get('frigo', false);
        $frigoIngredients = [];
        $formIngredients = [];

        // Traiter les ingrédients du frigo si demandé
        if ($frigo && $user->getFrigo()) {
            $frigoIngredients = $user->getFrigo()->getIngredientsHasFrigo()
                ->map(fn($ingredient) => [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName()
                ])
                ->toArray();
        }

        // Traiter les ingrédients du formulaire
        $ingredientsFormParam = $request->query->get('ingredientsForm', '');
        if (!empty($ingredientsFormParam)) {
            if (str_starts_with($ingredientsFormParam, '[')) {
                // Format JSON avec objets {id, name}
                $formIngredients = json_decode($ingredientsFormParam, true) ?? [];
            } else {
                // Format liste d'IDs séparés par des virgules
                $ids = array_map('intval', explode(',', $ingredientsFormParam));
                $ingredients = $this->ingredientRepository->findByIds($ids);
                $formIngredients = array_map(fn($ingredient) => [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName()
                ], $ingredients);
            }
        }

        return [
            'frigo' => $frigoIngredients,
            'form' => $formIngredients
        ];
    }
}