<?php

namespace App\Repository;

use App\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    // Exemples de méthodes perso si besoin :
    // public function findForUserAndRecipe(User $u, Recipe $r): ?Favorite
    // {
    //     return $this->createQueryBuilder('f')
    //         ->andWhere('f.user = :u')->setParameter('u', $u)
    //         ->andWhere('f.recipe = :r')->setParameter('r', $r)
    //         ->getQuery()->getOneOrNullResult();
    // }
}
