<?php

namespace App\Controller;

use App\Entity\Recipe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Statistiques utilisateur
        $userRecipes = $entityManager->getRepository(Recipe::class)
            ->findBy(['author' => $user]);



        // Calcul des statistiques
        $totalRecipes = count($userRecipes);
        $totalViews = array_sum(array_map(fn($recipe) => $recipe->getViews(), $userRecipes));


        // Calcul de la note moyenne
        $averageRating = 0;
        $totalRatings = 0;
        foreach ($userRecipes as $recipe) {
            if ($recipe->getRatings()->count() > 0) {
                $averageRating += $recipe->getAverageRating() * $recipe->getRatings()->count();
                $totalRatings += $recipe->getRatings()->count();
            }
        }
        $averageRating = $totalRatings > 0 ? round($averageRating / $totalRatings, 1) : 0;

        // Recettes les plus populaires
        $popularRecipes = $entityManager->getRepository(Recipe::class)
            ->createQueryBuilder('r')
            ->where('r.author = :user')
            ->setParameter('user', $user)
            ->orderBy('r.views', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Dernières recettes de la communauté
        $latestCommunityRecipes = $entityManager->getRepository(Recipe::class)
            ->createQueryBuilder('r')
            ->where('r.isPublic = :public')
            ->andWhere('r.author != :user')
            ->setParameter('public', true)
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // Déterminer le niveau de l'utilisateur
        $userLevel = $this->getUserLevel($totalRecipes, $averageRating, $totalViews);

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'stats' => [
                'totalRecipes' => $totalRecipes,
                'totalViews' => $totalViews,
                'averageRating' => $averageRating,
                'totalRatings' => $totalRatings,
            ],
            'userLevel' => $userLevel,
            'popularRecipes' => $popularRecipes,
            'latestCommunityRecipes' => $latestCommunityRecipes,
        ]);
    }

    private function getUserLevel(int $totalRecipes, float $averageRating, int $totalViews): array
    {
        $points = $totalRecipes * 10 + $totalViews * 2 + $averageRating * 20;

        if ($points >= 500) {
            return [
                'name' => 'Maître Orange Chef',
                'icon' => '👑',
                'color' => 'gold',
                'badge' => 'badge-gold',
                'description' => 'Expert culinaire reconnu !',
                'nextLevel' => null,
                'progress' => 100
            ];
        } elseif ($points >= 200) {
            return [
                'name' => 'Chef Orange Expérimenté',
                'icon' => '🥇',
                'color' => 'silver',
                'badge' => 'badge-silver',
                'description' => 'Cuisinier talentueux !',
                'nextLevel' => 'Maître Orange Chef',
                'progress' => round(($points - 200) / 300 * 100)
            ];
        } elseif ($points >= 50) {
            return [
                'name' => 'Chef Orange Débutant',
                'icon' => '🥉',
                'color' => 'bronze',
                'badge' => 'badge-bronze',
                'description' => 'En bonne voie !',
                'nextLevel' => 'Chef Orange Expérimenté',
                'progress' => round(($points - 50) / 150 * 100)
            ];
        } else {
            return [
                'name' => 'Apprenti Chef',
                'icon' => '🍊',
                'color' => 'orange',
                'badge' => 'badge-chef',
                'description' => 'Bienvenue dans l\'aventure !',
                'nextLevel' => 'Chef Orange Débutant',
                'progress' => round($points / 50 * 100)
            ];
        }
    }
}
