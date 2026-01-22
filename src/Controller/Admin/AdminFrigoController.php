<?php

namespace App\Controller\Admin;

use App\Entity\Frigo;
use App\Entity\Ingredient;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/frigo', name: 'admin_frigo_')]
class AdminFrigoController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    // --- 1. LISTER LES INGRÉDIENTS DU FRIGO ---
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);
        $frigo = $user->getFrigo();

        if (!$frigo) {
            return $this->json(['error' => 'Frigo non trouvé pour cet utilisateur'], 404);
        }

        $ingredients = $frigo->getIngredientsHasFrigo();

        $data = array_map(fn(Ingredient $i) => [
            'id' => $i->getId(),
            'name' => $i->getName(),
            'slug' => $i->getSlug()
        ], $ingredients->toArray());

        return $this->json($data);
    }

    // --- 2. AJOUTER UN INGRÉDIENT AU FRIGO ---
    #[Route('/{id}', name: 'add_ingredient', methods: ['POST'])]
    public function addIngredient(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);
        $frigo = $user->getFrigo();

        if (!$frigo) {
            // Créer un frigo si inexistant
            $frigo = new Frigo();
            $frigo->setUserFrigo($user);
            $em->persist($frigo);
        }

        // Vérifier si l'ingrédient est déjà dans le frigo
        if ($frigo->getIngredientsHasFrigo()->contains($ingredient)) {
            return $this->json(['message' => 'Ingrédient déjà dans le frigo'], 400);
        }

        // Ajouter l'ingrédient
        $frigo->addIngredientsHasFrigo($ingredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient ajouté au frigo'], 200);
    }

    // --- 3. SUPPRIMER UN INGRÉDIENT DU FRIGO ---
    #[Route('/{id}', name: 'remove_ingredient', methods: ['DELETE'])]
    public function removeIngredient(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);
        $frigo = $user->getFrigo();

        if (!$frigo) {
            return $this->json(['error' => 'Frigo non trouvé'], 404);
        }

        // Vérifier si l'ingrédient est dans le frigo
        if (!$frigo->getIngredientsHasFrigo()->contains($ingredient)) {
            return $this->json(['message' => 'Ingrédient non trouvé dans le frigo'], 400);
        }

        // Supprimer l'ingrédient
        $frigo->removeIngredientsHasFrigo($ingredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient supprimé du frigo'], 200);
    }
}