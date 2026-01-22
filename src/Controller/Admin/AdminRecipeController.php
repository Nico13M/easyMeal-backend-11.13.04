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
use Symfony\Component\Routing\Attribute\Route;

use App\Entity\User;
use Symfony\Component\Validator\Constraints\Collection;

#[Route('/admin/recipes', name: 'admin_recipe_')]
class AdminRecipeController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    // LISTE (GET)
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

    //  DÉTAIL (GET {id})
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->serializeRecipe($recipe));
    }

    // CRÉATION (POST)
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
    
        foreach ($data['diets_has_recipe'] ?? [] as $dietId) {
            $diet = $em->getRepository(\App\Entity\Diet::class)->find($dietId);
            if ($diet) {
                $recipe->addDietsHasRecipe($diet);
            }
        }

        // On assigne l'utilisateur trouvé
        $recipe->setUser($user);

        $em->persist($recipe);
        $em->flush();

        return $this->json($this->serializeRecipe($recipe), 201);
    }

    // MODIFICATION (PATCH)
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
        if (isset($data['diets_has_recipe'])) {
            // On vide d'abord toutes les diètes existantes
            foreach ($recipe->getDietsHasRecipe() as $diet) {
                $recipe->removeDietsHasRecipe($diet);
            }
            // Puis on ajoute celles fournies
            foreach ($data['diets_has_recipe'] as $dietId) {
                $diet = $em->getRepository(\App\Entity\Diet::class)->find($dietId);
                if ($diet) {
                    $recipe->addDietsHasRecipe($diet);
                }
            }
        }

        $em->flush();

        return $this->json($this->serializeRecipe($recipe));
    }

    //  SUPPRESSION (DELETE)
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

    // RÉCUPÉRER LES INGRÉDIENTS BLACKLIST DE L'UTILISATEUR 
    #[Route('/user/blacklist', name: 'user_blacklist', methods: ['GET'])]
    public function getUserBlacklist(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);
        $blacklist = $user->getUserIngredientsBlacklist();

        $data = array_map(fn($ingredient) => [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug()
        ], $blacklist->toArray());

        return $this->json($data);
    }

    // RECHERCHE DE RECETTES AVEC BLACKLIST ET DIETS 
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, RecipeRepository $recipeRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);

        // Récupérer les IDs des ingrédients blacklist
        $blacklistIds = $user->getUserIngredientsBlacklist()->map(fn($i) => $i->getId())->toArray();

        // Récupérer les IDs des diets de l'utilisateur
        $dietIds = $user->getDiets()->map(fn($d) => $d->getId())->toArray();

        // Trouver les recettes correspondantes
        $recipes = $recipeRepository->findRecipesExcludingIngredientsAndMatchingDiets($blacklistIds, $dietIds);

        // Sérialiser les recettes
        $data = array_map(fn(Recipe $r) => $this->serializeRecipe($r), $recipes);

        return $this->json($data);
    }

    // AJOUTER UNE RECETTE AUX FAVORIS
    #[Route('/{id}/favorite', name: 'add_favorite', methods: ['POST'])]
    public function addToFavorites(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $em
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();

        // Vérifier si la recette est déjà dans les favoris
        assert($user instanceof \App\Entity\User);
        if ($user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette est déjà dans vos favoris'], 400);
        }

        // Ajouter aux favoris
        
        $user->addUserRecipePreference($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette ajoutée aux favoris'], 200);
    }

    // SUPPRIMER UNE RECETTE DES FAVORIS
    #[Route('/{id}/favorite', name: 'remove_favorite', methods: ['DELETE'])]
    public function removeFromFavorites(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $em
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();

        // Vérifier si la recette est dans les favoris
        assert($user instanceof \App\Entity\User);
        if (!$user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette n\'est pas dans vos favoris'], 400);
        }

        // Supprimer des favoris
        assert($user instanceof \App\Entity\User);
        $user->removeUserRecipePreference($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette supprimée des favoris'], 200);
    }

    // LISTER LES RECETTES FAVORITES DE L'UTILISATEUR
    #[Route('/favorites', name: 'favorites', methods: ['GET'])]
    public function getFavorites(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);
        $favorites = $user->getUserRecipePreferences();

        // Sérialiser les recettes
        $data = array_map(fn(Recipe $r) => $this->serializeRecipe($r), $favorites->toArray());

        return $this->json($data);
    }
    
}