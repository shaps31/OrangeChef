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
    public function toggle(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('fav_'.$recipe->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Option: empêcher de « liker » une recette privée si on n’est pas l’auteur/admin
        if (method_exists($recipe, 'isPublic') && !$recipe->isPublic()
            && $recipe->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $repo = $em->getRepository(Favorite::class);
        $fav  = $repo->findOneBy(['recipe' => $recipe, 'user' => $this->getUser()]);
        $state = 'added';

        if ($fav) {
            $em->remove($fav);
            $state = 'removed';
            $this->addFlash('success', 'Retiré des favoris.');
        } else {
            $em->persist((new Favorite())->setRecipe($recipe)->setUser($this->getUser()));
            $this->addFlash('success', 'Ajouté aux favoris.');
        }
        $em->flush();

        if ($request->isXmlHttpRequest() || $request->getPreferredFormat() === 'json') {
            return $this->json(['state' => $state]);
        }


        //  redirection vers la page d’où on vient (sécurisée au même host)
        $return = $request->request->get('return') ?? $request->headers->get('referer');
        if ($return && parse_url($return, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($return, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_recipe_show', [
            '_locale'   => $request->getLocale(),
            'id'        => $recipe->getId(),
            '_fragment' => 'top',
        ], Response::HTTP_SEE_OTHER);
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
