<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')] // toute action ici nécessite un utilisateur connecté
final class DashboardController extends AbstractController
{
    // Petites constantes pour éviter les “valeurs magiques”
    private const POPULAR_LIMIT = 5;   // combien de recettes “populaires” afficher
    private const LATEST_TAKE   = 6;   // combien de recettes récentes de la communauté
    private const LATEST_FETCH  = 12;  // on en récupère un peu plus puis on filtre

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(RecipeRepository $recipes): Response
    {
        // 1) Utilisateur courant (garanti non nul grâce à #[IsGranted] ci-dessus)
        $user = $this->getUser();
        if (!$user) {
            // Ceinture + bretelles si jamais
            return $this->redirectToRoute('app_login');
        }

        // 2) Mes recettes (les miennes), triées des plus récentes aux plus anciennes
        $userRecipes = $recipes->findBy(
            ['author' => $user],
            ['createdAt' => 'DESC']
        );

        // 3) Statistiques simples
        $totalRecipes = \count($userRecipes);

        // total des vues (on force en int au cas où)
        $totalViews = array_sum(array_map(
            fn (Recipe $r) => (int) $r->getViews(),
            $userRecipes
        ));

        // moyenne des notes “pondérée” par le nombre de notes de chaque recette
        $sumWeighted  = 0.0;
        $totalRatings = 0;
        foreach ($userRecipes as $r) {
            $count = $r->getRatings()->count();   // suppose une relation “ratings”
            if ($count > 0) {
                $sumWeighted  += ((float) $r->getAverageRating()) * $count; // suppose un getter “moyenne”
                $totalRatings += $count;
            }
        }
        $averageRating = $totalRatings > 0 ? round($sumWeighted / $totalRatings, 1) : 0.0;

        // 4) Recettes populaires (par vues) — toujours mes recettes
        $popularRecipes = $recipes->findBy(
            ['author' => $user],
            ['views' => 'DESC'],
            self::POPULAR_LIMIT
        );

        // 5) Dernières recettes publiques de la communauté (hors moi)
        //    Version simple : on prend les 12 dernières publiques, on retire les miennes et on garde 6
        $latestPublic = $recipes->findBy(
            ['isPublic' => true],
            ['createdAt' => 'DESC'],
            self::LATEST_FETCH
        );
        $latestCommunityRecipes = array_slice(
            array_values(array_filter(
                $latestPublic,
                fn (Recipe $r) => $r->getAuthor() !== $user
            )),
            0,
            self::LATEST_TAKE
        );

        // 6) “Niveau” utilisateur (mini gamification)
        $userLevel = $this->getUserLevel($totalRecipes, $averageRating, $totalViews);

        // 7) Rendu
        return $this->render('dashboard/index.html.twig', [
            'user'  => $user,
            'stats' => [
                'totalRecipes'  => $totalRecipes,
                'totalViews'    => $totalViews,
                'averageRating' => $averageRating,
                'totalRatings'  => $totalRatings,
            ],
            'userLevel'              => $userLevel,
            'popularRecipes'         => $popularRecipes,
            'latestCommunityRecipes' => $latestCommunityRecipes,
        ]);
    }

    /** Calcul très simple d'un “niveau” utilisateur (juste pour le fun) */
    private function getUserLevel(int $totalRecipes, float $averageRating, int $totalViews): array
    {
        // barème très basique : chaque recette, chaque vue et la moyenne jouent un rôle
        $points = $totalRecipes * 10 + $totalViews * 2 + $averageRating * 20;

        if ($points >= 500) {
            return [
                'name'        => 'Maître Orange Chef',
                'icon'        => '👑',
                'color'       => 'gold',
                'badge'       => 'badge-gold',
                'description' => 'Expert culinaire reconnu !',
                'nextLevel'   => null,
                'progress'    => 100,
            ];
        }

        if ($points >= 200) {
            return [
                'name'        => 'Chef Orange Expérimenté',
                'icon'        => '🥇',
                'color'       => 'silver',
                'badge'       => 'badge-silver',
                'description' => 'Cuisinier talentueux !',
                'nextLevel'   => 'Maître Orange Chef',
                'progress'    => max(0, min(100, (int) round(($points - 200) / 300 * 100))),
            ];
        }

        if ($points >= 50) {
            return [
                'name'        => 'Chef Orange Débutant',
                'icon'        => '🥉',
                'color'       => 'bronze',
                'badge'       => 'badge-bronze',
                'description' => 'En bonne voie !',
                'nextLevel'   => 'Chef Orange Expérimenté',
                'progress'    => max(0, min(100, (int) round(($points - 50) / 150 * 100))),
            ];
        }

        return [
            'name'        => 'Apprenti Chef',
            'icon'        => '🍊',
            'color'       => 'orange',
            'badge'       => 'badge-chef',
            'description' => "Bienvenue dans l'aventure !",
            'nextLevel'   => 'Chef Orange Débutant',
            'progress'    => max(0, min(100, (int) round($points / 50 * 100))),
        ];
    }
}
