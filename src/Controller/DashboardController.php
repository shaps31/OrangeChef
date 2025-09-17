<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(RecipeRepository $recipes): Response
    {
        $user = $this->getUser();
        if (!$user) {
            // Sécurité au cas où
            return $this->redirectToRoute('app_login');
        }

        // — Mes recettes
        $userRecipes = $recipes->findBy(
            ['author' => $user],
            ['createdAt' => 'DESC']
        );

        // — Statistiques simples
        $totalRecipes = count($userRecipes);
        $totalViews   = array_sum(array_map(fn(Recipe $r) => (int) $r->getViews(), $userRecipes));

        // — Note moyenne (pondérée par le nombre de notes de chaque recette)
        $averageRating = 0.0;
        $totalRatings  = 0;
        foreach ($userRecipes as $r) {
            $count = $r->getRatings()->count();     // suppose une relation ratings
            if ($count > 0) {
                $averageRating += ($r->getAverageRating() * $count); // suppose un getter moyen
                $totalRatings  += $count;
            }
        }
        $averageRating = $totalRatings > 0 ? round($averageRating / $totalRatings, 1) : 0.0;

        // — Recettes populaires (mes recettes triées par vues)
        $popularRecipes = $recipes->findBy(
            ['author' => $user],
            ['views' => 'DESC'],
            5
        );

        // — Dernières recettes publiques de la communauté (hors moi)
        //   (Version débutant : on prend les 12 dernières publiques et on filtre en PHP)
        $latestPublic = $recipes->findBy(
            ['isPublic' => true],
            ['createdAt' => 'DESC'],
            12
        );
        $latestCommunityRecipes = array_values(array_slice(
            array_filter($latestPublic, fn(Recipe $r) => $r->getAuthor() !== $user),
            0,
            6
        ));

        // — “Niveau” utilisateur (gamification très simple)
        $userLevel = $this->getUserLevel($totalRecipes, $averageRating, $totalViews);

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'stats' => [
                'totalRecipes'   => $totalRecipes,
                'totalViews'     => $totalViews,
                'averageRating'  => $averageRating,
                'totalRatings'   => $totalRatings,
            ],
            'userLevel'             => $userLevel,
            'popularRecipes'        => $popularRecipes,
            'latestCommunityRecipes'=> $latestCommunityRecipes,
        ]);
    }

    /** Calcul très simple d'un “niveau” utilisateur */
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
                'progress' => 100,
            ];
        }

        if ($points >= 200) {
            return [
                'name' => 'Chef Orange Expérimenté',
                'icon' => '🥇',
                'color' => 'silver',
                'badge' => 'badge-silver',
                'description' => 'Cuisinier talentueux !',
                'nextLevel' => 'Maître Orange Chef',
                'progress' => max(0, min(100, (int) round(($points - 200) / 300 * 100))),
            ];
        }

        if ($points >= 50) {
            return [
                'name' => 'Chef Orange Débutant',
                'icon' => '🥉',
                'color' => 'bronze',
                'badge' => 'badge-bronze',
                'description' => 'En bonne voie !',
                'nextLevel' => 'Chef Orange Expérimenté',
                'progress' => max(0, min(100, (int) round(($points - 50) / 150 * 100))),
            ];
        }

        return [
            'name' => 'Apprenti Chef',
            'icon' => '🍊',
            'color' => 'orange',
            'badge' => 'badge-chef',
            'description' => 'Bienvenue dans l\'aventure !',
            'nextLevel' => 'Chef Orange Débutant',
            'progress' => max(0, min(100, (int) round($points / 50 * 100))),
        ];
    }
}
