<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search/suggest', name: 'app_search_suggest', methods: ['GET'])]
    public function suggest(Request $request, RecipeRepository $recipes): JsonResponse
    {
        $term = trim((string) ($request->query->get('q') ?? $request->query->get('term') ?? ''));
        if (mb_strlen($term) < 2) {
            return $this->json(['items' => []]);
        }

        $q = '%'.mb_strtolower($term).'%';
        $items = [];

        // Catégories (distinct, non vides)
        $cats = $recipes->createQueryBuilder('r')
            ->select('DISTINCT r.category AS category')
            ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
            ->andWhere('r.category IS NOT NULL AND r.category <> \'\'')
            ->andWhere('LOWER(r.category) LIKE :q')->setParameter('q', $q)
            ->orderBy('category', 'ASC')
            ->setMaxResults(3)
            ->getQuery()->getScalarResult();

        foreach ($cats as $row) {
            $cat = $row['category'];
            $items[] = [
                'label' => $cat,
                'type'  => 'category',
                'url'   => $this->generateUrl('app_recipe_index', [
                    '_locale'  => $request->getLocale(),
                    'category' => $cat,
                    'q'        => $term,
                ]),
            ];
        }

        // Titres de recettes (contains sur titre + description)
        $rows = $recipes->createQueryBuilder('r')
            ->select('r.id, r.title')
            ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
            ->andWhere('(LOWER(r.title) LIKE :q OR LOWER(r.description) LIKE :q)')
            ->setParameter('q', $q)
            ->orderBy('r.title', 'ASC')
            ->setMaxResults(8)
            ->getQuery()->getArrayResult();

        foreach ($rows as $r) {
            $items[] = [
                'label' => $r['title'],
                'type'  => 'recipe',
                'url'   => $this->generateUrl('app_recipe_show', [
                    '_locale' => $request->getLocale(),
                    'id'      => $r['id'],
                ]),
            ];
        }

        return $this->json(['items' => $items]);
    }
}
