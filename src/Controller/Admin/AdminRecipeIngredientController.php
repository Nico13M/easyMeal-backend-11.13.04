<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\RecipeIngredient;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/admin/recipe-ingredients', name: 'admin_recipe_ingredient_')]
class AdminRecipeIngredientController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    // AJOUTER UN INGRÉDIENT À UNE RECETTE (POST)
    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // 1. Validation des champs obligatoires
        if (empty($data['recipe_id']) || empty($data['ingredient_id']) || empty($data['quantity'])) {
            return $this->json(['error' => 'recipe_id, ingredient_id et quantity sont obligatoires'], 400);
        }

        // 2. Récupération des entités liées
        $recipe = $em->getRepository(Recipe::class)->find($data['recipe_id']);
        $ingredient = $em->getRepository(Ingredient::class)->find($data['ingredient_id']);

        if (!$recipe) return $this->json(['error' => 'Recette introuvable'], 404);
        if (!$ingredient) return $this->json(['error' => 'Ingrédient introuvable'], 404);

        // 3. Création de la liaison (Le Pivot)
        $link = new RecipeIngredient();
        $link->setRecipe($recipe);
        $link->setIngredient($ingredient);
        $link->setQuantity((int)$data['quantity']); // Force en entier
        $link->setUnit($data['unit'] ?? null); // Unité optionnelle (ex: 'g', 'ml', 'pincée')

        $em->persist($link);
        $em->flush();

        return $this->json([
            'message' => 'Ingrédient ajouté à la recette !',
            'id' => $link->getId(),
            'recipe' => $recipe->getName(),
            'ingredient' => $ingredient->getName(),
            'quantity' => $link->getQuantity(),
            'unit' => $link->getUnit()
        ], 201);
    }

    // RETIRER UN INGRÉDIENT (DELETE)
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        RecipeIngredient $recipeIngredient,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($recipeIngredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient retiré de la recette'], 204);
    }
}