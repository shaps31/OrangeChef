<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // On dit au parent : ce repository gère l'entité Recipe
        parent::__construct($registry, Recipe::class);
    }

    /**
     * Recherche dans les recettes publiques.
     * - $q : mot-clé (peut être vide)
     * - $category / $difficulty : filtres optionnels
     * Retourne un tableau d'objets Recipe.
     */
    public function search(string $q, ?string $category = null, ?string $difficulty = null): array
    {
        // 1) On crée un "QueryBuilder" = constructeur de requête SQL pour l'entité Recipe (alias "r")
        $qb = $this->createQueryBuilder('r')

            // 2) On ne garde que les recettes "publiques"
            ->andWhere('r.isPublic = :public')
            ->setParameter('public', true);

        // 3) On prépare le mot-clé : on enlève les espaces autour + on passe en minuscule
        //    (pour faire une recherche "insensible" aux majuscules/minuscules)
        $q = trim(mb_strtolower($q));

        // 4) Si un mot-clé est fourni, on cherche ce mot dans plusieurs colonnes
        if ($q !== '') {
            $qb->andWhere('
                LOWER(r.title)       LIKE :term OR
                LOWER(r.description) LIKE :term OR
                LOWER(r.ingredients) LIKE :term OR
                LOWER(r.category)    LIKE :term
            ')
                // :term est un "paramètre" sécurisé → évite d'injecter le mot-clé directement dans le SQL
                ->setParameter('term', '%'.$q.'%'); // LIKE "%mot%"
        }

        // 5) Filtre optionnel : catégorie exacte
        if (!empty($category)) {
            $qb->andWhere('r.category = :category')
                ->setParameter('category', $category);
        }

        // 6) Filtre optionnel : difficulté exacte
        if (!empty($difficulty)) {
            $qb->andWhere('r.difficulty = :difficulty')
                ->setParameter('difficulty', $difficulty);
        }

        // 7) Tri par date (les plus récentes d'abord) + on limite à 50 résultats pour rester léger
        return $qb->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()   // on transforme le QueryBuilder en requête
            ->getResult(); // on exécute et on récupère les entités Recipe
    }

    /**
     * Renvoie les dernières recettes **publiques**.
     * - $limit : combien d’éléments on veut (3 par défaut)
     * - utilise findBy(criteria, orderBy, limit)
     */
    public function findLatestPublic(int $limit = 3): array
    {
        return $this->findBy(
            ['isPublic' => true],        // WHERE is_public = 1
            ['createdAt' => 'DESC'],     // ORDER BY created_at DESC (plus récentes d'abord)
            $limit                       // LIMIT ?
        );
    }

    /**
     * Compte combien de recettes **publiques** existent.
     * - retourne un entier (0, 1, 2, …)
     */
    public function countPublic(): int
    {
        return $this->count(['isPublic' => true]); // SELECT COUNT(*) WHERE is_public = 1
    }
    public function suggestByTitle(string $term, int $limit = 8): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id, r.title')
            ->andWhere('LOWER(r.title) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($term).'%')
            ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
            ->orderBy('r.views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function suggestCategories(string $term, int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.category AS cat')
            ->andWhere('r.category IS NOT NULL AND r.category <> \'\'')
            ->andWhere('LOWER(r.category) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($term).'%')
            ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(fn($r) => $r['cat'] ?? null, $rows)));
    }


}
