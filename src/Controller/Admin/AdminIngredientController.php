<?php

namespace App\Controller\Admin;

use App\Entity\Ingredient;
use App\Repository\IngredientRepository;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/ingredients', name: 'admin_ingredient_')]
class AdminIngredientController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    // --- 1. LISTE (GET) ---
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, IngredientRepository $ingredientRepository): Response
    {
        // 🔒 Sécurité : Vérifier si l'admin est connecté
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $ingredients = $ingredientRepository->findAll();

        // On formate les données manuellement pour éviter le bug des objets vides "{}"
        $data = array_map(fn(Ingredient $ing) => $this->serializeIngredient($ing), $ingredients);

        return $this->json($data);
    }

    // --- 2. DÉTAIL (GET {id}) ---
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request, Ingredient $ingredient): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->serializeIngredient($ingredient));
    }

    // --- 3. CRÉATION (POST) ---
    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        // 🔒 1. Auth
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        // 🔒 2. CSRF (Vérifie que la requête vient bien de ton app)
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Validation
        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }

        // Vérification doublon
        $existing = $em->getRepository(Ingredient::class)->findOneBy(['name' => $data['name']]);
        if ($existing) {
            return $this->json(['error' => 'Cet ingrédient existe déjà'], 409);
        }

        $ingredient = new Ingredient();
        $ingredient->setName($data['name']);
        // Le slug et les dates sont gérés par Gedmo automatiquement !

        $em->persist($ingredient);
        $em->flush();

        return $this->json($this->serializeIngredient($ingredient), 201);
    }

    // --- 4. MODIFICATION (PATCH) ---
    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])]
    public function edit(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): Response {
        // 🔒 Auth & CSRF
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']) && !empty($data['name'])) {
            $ingredient->setName($data['name']);
            // Le slug se mettra à jour tout seul si le nom change
        }

        $em->flush();

        return $this->json($this->serializeIngredient($ingredient));
    }

    // --- 5. SUPPRESSION (DELETE) ---
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
        // 🔒 Auth & CSRF
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($ingredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient supprimé'], 204);
    }

    /**
     * Petite fonction privée pour transformer l'objet en tableau propre.
     * C'est ce qui règle ton problème de résultat "{}" vide.
     */
    private function serializeIngredient(Ingredient $ingredient): array
    {
        return [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug(),
            'created_at' => $ingredient->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $ingredient->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}