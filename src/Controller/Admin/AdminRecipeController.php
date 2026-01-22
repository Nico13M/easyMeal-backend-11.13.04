<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Repository\IngredientRepository;
use App\Service\UserDataService;
use App\Service\DataService;
use App\Service\UserManager;
use App\Service\RecipeService;
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
        private IngredientRepository $ingredientRepository
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

        // 1) Récupérer l'utilisateur connecté 
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);


        // 2) Récupérer les ingrédients blacklist par l'utilisateur
        $blacklistIds = $user->getUserIngredientsBlacklist()->map(fn($i) => $i->getId())->toArray();

        // 3) Récupérer les régimes de l'utilisateur
        $dietIds = $user->getDiets()->map(fn($d) => $d->getId())->toArray();

        // 4) Gestion des ingrédients
        $frigo = $request->query->get('frigo', false);
        $frigoIngredientIds = [];

        // Si frigo est vrai, on récupère les ingrédients du frigo
        if ($frigo) {
            if ($user->getFrigo()) {
                $frigoIngredients = $user->getFrigo()->getIngredientsHasFrigo()->map(fn($i) => [
                    'id' => $i->getId(),
                    'name' => $i->getName()
                ])->toArray();
            }
        }

        // Qu'il soit vrai ou faux, on regarde ingredientsForm pour les ingrédients supplémentaires
        $ingredientsForm = [];
        $ingredientsFormParam = $request->query->get('ingredientsForm', '');
        if (!empty($ingredientsFormParam)) {
            // ingredientsForm peut être une chaîne JSON ou une liste d'IDs séparés par des virgules
            if (str_starts_with($ingredientsFormParam, '[')) {
                // C'est du JSON avec des objets {id, name}
                $ingredientsForm = json_decode($ingredientsFormParam, true) ?? [];
            } else {
                // C'est une liste séparés par des virgules - on récupère les objets complets depuis la DB
                $ids = array_map('intval', explode(',', $ingredientsFormParam));
                $ingredients = $this->ingredientRepository->findByIds($ids);
                $ingredientsForm = array_map(fn($ingredient) => [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName()
                ], $ingredients);
            }
        }

        // Combiner les ingrédients du frigo et ceux du formulaire
        $allIngredients = array_merge($frigoIngredients, $ingredientsForm);

        // 5) On envoi ses informations via le DataService qui va envoyé les données à un endpoint
        $searchData = [
            'blacklist_ids' => $blacklistIds,
            'diet_ids' => $dietIds,
            'frigo_ingredients' => $frigoIngredients,
            'form_ingredients' => $ingredientsForm,
            'all_ingredients' => $allIngredients,
        ];

        // Créer un service spécifique pour la recherche ou utiliser DataService
        $result = $this->dataService->sendSearchData($searchData, $user->getId());

        if (!$result['success']) {
            return $this->json([
                'error' => 'Erreur lors de la recherche de recettes',
                'details' => $result['error'] ?? 'Erreur inconnue'
            ], 500);
        }

        // 6) Le endpoint va renvoyer la recette ou les recettes à l'utilisateur
        $recipesData = $result['data']['recipes'] ?? [];

        // 7) On renvoit en json les réponses pour que le front puisse récupérer la recette
        return $this->json([
            'recipes' => $recipesData,
            'search_criteria' => $searchData,
            'total_results' => count($recipesData)
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