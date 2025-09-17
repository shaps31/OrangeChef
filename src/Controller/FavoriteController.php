<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FavoriteController extends AbstractController
{
    #[Route('/recipes/{id<\d+>}/favorite', name: 'app_favorite_toggle', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function toggle(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        // CSRF
        if (!$this->isCsrfTokenValid('fav_'.$recipe->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $repo = $em->getRepository(Favorite::class);
        $fav  = $repo->findOneBy(['recipe' => $recipe, 'user' => $this->getUser()]);

        if ($fav) {
            // retirer des favoris
            $em->remove($fav);
            $this->addFlash('success', 'Retiré des favoris.');
        } else {
            // ajouter aux favoris
            $fav = (new Favorite())
                ->setRecipe($recipe)
                ->setUser($this->getUser());
            $em->persist($fav);
            $this->addFlash('success', 'Ajouté aux favoris.');
        }

        $em->flush();

        // Retour à la fiche recette
        return $this->redirectToRoute('app_recipe_show', [
            'id'        => $recipe->getId(),
            '_fragment' => 'top',
        ]);
    }
    #[Route('/favorites', name: 'app_favorites', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $em): Response
    {
        // Charge les favoris + la recette associée
        $favorites = $em->getRepository(Favorite::class)
            ->createQueryBuilder('f')
            ->addSelect('r')
            ->join('f.recipe', 'r')
            ->where('f.user = :u')
            ->setParameter('u', $this->getUser())
            ->orderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('favorite/index.html.twig', [
            'favorites' => $favorites,
        ]);

    }

}
