<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    //    /**
    //     * @return Review[] Returns an array of Review objects
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

    //    public function findOneBySomeField($value): ?Review
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function getApprovedStats(\App\Entity\Recipe $recipe): array
    {
        $res = $this->createQueryBuilder('rv')
            ->select('COALESCE(AVG(rv.rating), 0) AS avg', 'COUNT(rv.id) AS cnt')
            ->where('rv.recipe = :recipe')
            ->andWhere('rv.isApproved = :approved')
            ->setParameter('recipe', $recipe)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'avg'   => (float) $res['avg'],
            'count' => (int) $res['cnt'],
        ];
    }
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isApproved = :p')->setParameter('p', false)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isApproved = :p')->setParameter('p', false)
            ->getQuery()->getSingleScalarResult();
    }


}
