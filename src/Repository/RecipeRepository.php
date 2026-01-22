<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    //    /**
    //     * @return Recipe[] Returns an array of Recipe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function findRecipesExcludingIngredientsAndMatchingDiets(array $blacklistIngredientIds, array $dietIds): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.recipeIngredients', 'ri')
            ->leftJoin('r.diets_has_recipe', 'd')
            ->where('r.is_public = :isPublic')
            ->setParameter('isPublic', true);

        // Exclure les recettes qui ont des ingrédients dans la blacklist
        if (!empty($blacklistIngredientIds)) {
            $qb->andWhere(
                $qb->expr()->not(
                    $qb->expr()->exists(
                        $this->getEntityManager()->createQueryBuilder()
                            ->select('ri2.id')
                            ->from('App\Entity\RecipeIngredient', 'ri2')
                            ->where('ri2.recipe = r.id')
                            ->andWhere('ri2.ingredient IN (:blacklistIds)')
                            ->getDQL()
                    )
                )
            )->setParameter('blacklistIds', $blacklistIngredientIds);
        }

        // Inclure seulement les recettes qui ont au moins une diet dans dietIds
        if (!empty($dietIds)) {
            $qb->andWhere('d.id IN (:dietIds)')
                ->setParameter('dietIds', $dietIds);
        }

        return $qb->getQuery()->getResult();
    }
}