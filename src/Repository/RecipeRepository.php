<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * Recherche libre (titre, description, ingrédients, catégorie),
     * uniquement sur les recettes publiques.
     */
    public function search(string $q, ?string $category = null, ?string $difficulty = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isPublic = :public')->setParameter('public', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(50);

        $q = mb_strtolower(trim($q));
        if ($q !== '') {
            $qb->andWhere('
                LOWER(r.title)       LIKE :q
             OR LOWER(r.titre)       LIKE :q
             OR LOWER(r.description) LIKE :q
             OR LOWER(r.ingredients) LIKE :q
             OR LOWER(r.category)    LIKE :q
            ')
                ->setParameter('q', '%'.$q.'%');
        }

        if ($category) {
            $qb->andWhere('r.category = :category')
                ->setParameter('category', $category);
        }

        if ($difficulty) {
            $qb->andWhere('r.difficulty = :difficulty')
                ->setParameter('difficulty', $difficulty);
        }

        return $qb->getQuery()->getResult();
    }
}
