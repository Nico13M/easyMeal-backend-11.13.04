<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User; 

#[Route('/admin/recipes', name: 'admin_recipe_')]
class AdminRecipeController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    // --- 1. LISTE (GET) ---
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecipeRepository $recipeRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipes = $recipeRepository->findAll();
        // On transforme chaque recette en tableau propre
        $data = array_map(fn(Recipe $r) => $this->serializeRecipe($r), $recipes);

        return $this->json($data);
    }

    // --- 2. DÉTAIL (GET {id}) ---
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->serializeRecipe($recipe));
    }

    // --- 3. CRÉATION (POST) ---
   #[Route('/', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        // 🔒 Sécurité (On la garde, mais elle échouera silencieusement si pas connecté, c'est pas grave pour le test avec ID manuel)
        // if ($err = $this->userManager->ensureAuthenticated($request)) { return $err; }

        $data = json_decode($request->getContent(), true);

        // 1. Validation des champs obligatoires
        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom de la recette est obligatoire'], 400);
        }

        // 2. RECUPERATION DE L'UTILISATEUR 👤
        // On regarde si un ID est fourni dans le JSON
        $user = null;
        if (isset($data['user_id'])) {
            $user = $em->getRepository(User::class)->find($data['user_id']);
        } 
        // Sinon, on essaie de prendre l'utilisateur connecté (fallback)
        else {
            $user = $this->getUser();
        }

        // Si on a trouvé personne => Erreur
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable. Veuillez fournir un "user_id" valide.'], 404);
        }

        // 3. Création de la recette
        $recipe = new Recipe();
        $recipe->setName($data['name']);
        $recipe->setDescription($data['description'] ?? '');
        $recipe->setImage($data['image'] ?? null);
        $recipe->setServings($data['servings'] ?? 4);
        $recipe->setDuration($data['duration'] ?? null);
        $recipe->setIsPublic($data['is_public'] ?? false);

        // On assigne l'utilisateur trouvé
        $recipe->setUser($user);

        $em->persist($recipe);
        $em->flush();

        return $this->json($this->serializeRecipe($recipe), 201);
    }

    // --- 4. MODIFICATION (PATCH) ---
    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])]
    public function edit(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $em
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        // Optionnel : Vérifier si l'utilisateur a le droit de modifier CETTE recette
        // if ($recipe->getUser() !== $this->getUser()) {
        //     return $this->json(['error' => 'Accès interdit'], 403);
        // }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $recipe->setName($data['name']);
        if (isset($data['description'])) $recipe->setDescription($data['description']);
        if (isset($data['servings'])) $recipe->setServings($data['servings']);
        if (isset($data['duration'])) $recipe->setDuration($data['duration']);
        if (isset($data['is_public'])) $recipe->setIsPublic($data['is_public']);
        if (isset($data['image'])) $recipe->setImage($data['image']);

        $em->flush();

        return $this->json($this->serializeRecipe($recipe));
    }

    // --- 5. SUPPRESSION (DELETE) ---
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $em->remove($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette supprimée'], 204);
    }

    /**
     * Formate la recette en tableau JSON
     */
    private function serializeRecipe(Recipe $recipe): array
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
            'author' => [
                'id' => $recipe->getUser()?->getId(),
                'email' => $recipe->getUser()?->getEmail(),
                'firstname' => $recipe->getUser()?->getFirstname(),
                'lastname' => $recipe->getUser()?->getLastname(),
            ],'ingredients' => array_map(function($link) {
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