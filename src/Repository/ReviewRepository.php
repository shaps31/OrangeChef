<?php

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des avis (Review).
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // On indique au parent que ce repo gère l'entité Review
        parent::__construct($registry, Review::class);
    }

    /**
     * Statistiques des AVIS APPROUVÉS d'une recette :
     * - moyenne des notes
     * - nombre total d'avis
     *
     * Retourne un tableau simple : ['avg' => float, 'count' => int]
     */
    public function getApprovedStats(Recipe $recipe): array
    {
        // 1) Construire la requête : moyenne + nombre
        $qb = $this->createQueryBuilder('rv')
            ->select('COALESCE(AVG(rv.rating), 0) AS avgRating', 'COUNT(rv.id) AS total')
            ->where('rv.recipe = :recipe')
            ->andWhere('rv.isApproved = :approved')
            ->setParameter('recipe', $recipe)
            ->setParameter('approved', true);

        // 2) Exécuter et récupérer la ligne de résultat
        $row = $qb->getQuery()->getSingleResult();

        // 3) Caster proprement et renvoyer un tableau lisible
        return [
            'avg'   => (float) ($row['avgRating'] ?? 0),
            'count' => (int)   ($row['total'] ?? 0),
        ];
    }

    /**
     * Liste des AVIS EN ATTENTE (non approuvés), du plus récent au plus ancien.
     *
     * @return Review[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte combien d’AVIS sont EN ATTENTE.
     */
    // src/Repository/ReviewRepository.php
    public function countPending(): int
    {
        try {
            return (int) $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->andWhere('r.isApproved = 0')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Throwable) {


            return 0;
        }
    }


    public function countApproved(): int
    {
        return $this->count(['isApproved' => true]);
    }

}
