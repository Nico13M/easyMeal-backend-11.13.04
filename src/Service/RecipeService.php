<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class RecipeService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Crée une nouvelle recette à partir des données fournies
     */
    public function createRecipe(array $data, ?User $user = null): Recipe|JsonResponse
    {
        // 1. Validation des champs obligatoires
        if (empty($data['name'])) {
            return new JsonResponse(['error' => 'Le nom de la recette est obligatoire'], 400);
        }

        // 2. RECUPERATION DE L'UTILISATEUR 
        if (!$user && isset($data['user_id'])) {
            $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        }

        // Si on a trouvé personne => Erreur
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable. Veuillez fournir un "user_id" valide.'], 404);
        }

        // 3. Création de la recette
        $recipe = new Recipe();
        $recipe->setName($data['name']);
        $recipe->setDescription($data['description'] ?? '');
        $recipe->setImage($data['image'] ?? null);
        $recipe->setServings($data['servings'] ?? 4);
        $recipe->setDuration($data['duration'] ?? null);
        $recipe->setIsPublic($data['is_public'] ?? false);

        // Ajouter les diets
        foreach ($data['diets_has_recipe'] ?? [] as $dietId) {
            $diet = $this->entityManager->getRepository(\App\Entity\Diet::class)->find($dietId);
            if ($diet) {
                $recipe->addDietsHasRecipe($diet);
            }
        }

        // On assigne l'utilisateur trouvé
        $recipe->setUser($user);

        // Persister la recette
        $this->entityManager->persist($recipe);
        $this->entityManager->flush();

        return $recipe;
    }

    /**
     * Sérialise une recette en tableau pour la réponse JSON
     */
    public function serializeRecipe(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'name' => $recipe->getName(),
            'slug' => $recipe->getSlug(),
            'description' => $recipe->getDescription(),
            'image' => $recipe->getImage(),
            'servings' => $recipe->getServings(),
            'duration' => $recipe->getDuration(),
            'is_public' => $recipe->isPublic(),
            'created_at' => $recipe->getCreatedAt()?->format('Y-m-d H:i:s'),
            'diets_has_recipe' => array_map(fn($diet) => [
                'id' => $diet->getId(),
                'name' => $diet->getName(),
                'slug' => $diet->getSlug()
            ], $recipe->getDietsHasRecipe()->toArray()),
            'author' => [
                'id' => $recipe->getUser()?->getId(),
                'email' => $recipe->getUser()?->getEmail(),
                'firstname' => $recipe->getUser()?->getFirstname(),
                'lastname' => $recipe->getUser()?->getLastname(),
            ],
            'ingredients' => array_map(function($link) {
                return [
                    'id' => $link->getIngredient()->getId(),
                    'name' => $link->getIngredient()->getName(),
                    'slug' => $link->getIngredient()->getSlug(),
                    'quantity' => $link->getQuantity(),
                    'unit' => $link->getUnit(),
                ];
            }, $recipe->getRecipeIngredients()->toArray())
        ];
    }
}