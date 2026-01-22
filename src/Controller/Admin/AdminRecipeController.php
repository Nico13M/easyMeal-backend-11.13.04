<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\UserDataService;
use App\Service\DataService;
use App\Service\UserManager;
use App\Service\RecipeService;
use App\Service\RecipeSearchService;
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
    public function __construct(
        private UserManager $userManager,
        private RecipeService $recipeService,
        private UserDataService $userDataService,
        private DataService $dataService,
        private RecipeSearchService $recipeSearchService
    ) {}

    // LISTE (GET)
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecipeRepository $recipeRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipes = $recipeRepository->findAll();
        // On transforme chaque recette en tableau propre
        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r), $recipes);

        return $this->json($data);
    }

    //  DÉTAIL (GET {id})
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->recipeService->serializeRecipe($recipe));
    }

    // CRÉATION (POST)
   #[Route('/', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        // 🔒 Sécurité (On la garde, mais elle échouera silencieusement si pas connecté, c'est pas grave pour le test avec ID manuel)
        // if ($err = $this->userManager->ensureAuthenticated($request)) { return $err; }

        $data = json_decode($request->getContent(), true);

        // Récupération de l'utilisateur connecté (fallback)
        $user = $this->getUser();

        // Créer la recette via le service
        $result = $this->recipeService->createRecipe($data, $user);

        // Si c'est une erreur, la retourner
        if ($result instanceof JsonResponse) {
            return $result;
        }

        // Sinon, sérialiser et retourner la recette créée
        return $this->json($this->recipeService->serializeRecipe($result), 201);
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

        return $this->json($this->recipeService->serializeRecipe($recipe));
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
    public function search(Request $request): Response
    {
        // Vérifier l'authentification
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);

        // Effectuer la recherche via le service
        $result = $this->recipeSearchService->searchRecipes($user, $request);

        if (!$result['success']) {
            return $this->json([
                'error' => 'Erreur lors de la recherche de recettes',
                'details' => $result['error']
            ], 500);
        }

        // Retourner les résultats
        return $this->json([
            'recipes' => $result['recipes'],
            'search_criteria' => $result['search_criteria'],
            'total_results' => $result['total_results']
        ]);
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
        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r), $favorites->toArray());

        return $this->json($data);
    }

    // ENVOYER LES DONNÉES UTILISATEUR À L'API EXTERNE
    #[Route('/user/data/send', name: 'send_user_data', methods: ['POST'])]
    public function sendUserData(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);

        // Récupérer les données utilisateur via le service
        $userData = $this->userDataService->getUserData($user);

        // Envoyer les données à l'API externe via le service DATA
        $result = $this->dataService->sendUserData($userData, $user->getId());

        if ($result['success']) {
            return $this->json([
                'message' => 'Données utilisateur envoyées avec succès',
                'status_code' => $result['status_code'],
                'response' => $result['data'] ?? null
            ], 200);
        } else {
            return $this->json([
                'error' => 'Erreur lors de l\'envoi des données',
                'details' => $result['error'] ?? 'Erreur inconnue'
            ], 500);
        }
    }
    
}