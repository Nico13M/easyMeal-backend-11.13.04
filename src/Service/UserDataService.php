<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\IngredientRepository;
use App\Repository\DietRepository;

class UserDataService
{
    public function __construct(
        private IngredientRepository $ingredientRepository,
        private DietRepository $dietRepository
    ) {}

    /**
     * Récupère toutes les données nécessaires pour un utilisateur
     */
    public function getUserData(User $user): array
    {
        return [
            'ingredients' => $this->getAllIngredients(),
            'blacklist' => $this->getUserBlacklist($user),
            'diets' => $this->getUserDiets($user)
        ];
    }

    /**
     * Récupère tous les ingrédients disponibles
     */
    private function getAllIngredients(): array
    {
        $ingredients = $this->ingredientRepository->findAll();

        return array_map(fn($ingredient) => [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug()
        ], $ingredients);
    }

    /**
     * Récupère les ingrédients blacklistés de l'utilisateur
     */
    private function getUserBlacklist(User $user): array
    {
        $blacklist = $user->getUserIngredientsBlacklist();

        return array_map(fn($ingredient) => [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug()
        ], $blacklist->toArray());
    }

    /**
     * Récupère les diets de l'utilisateur
     */
    private function getUserDiets(User $user): array
    {
        $diets = $user->getDiets();

        return array_map(fn($diet) => [
            'id' => $diet->getId(),
            'name' => $diet->getName(),
            'slug' => $diet->getSlug()
        ], $diets->toArray());
    }
}