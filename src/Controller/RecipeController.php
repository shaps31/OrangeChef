<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\RecipeComment;
use App\Entity\RecipeRating;
use App\Entity\Favorite;
use App\Entity\Review;

use App\Form\RecipeType;
use App\Form\RecipeCommentType;
use App\Form\ReviewType;

use App\Repository\RecipeRepository;
use App\Repository\ReviewRepository;

use App\Service\Notification;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/recettes')]
final class RecipeController extends AbstractController
{
    /** Liste des recettes publiques + petits filtres  */
    #[Route('/', name: 'app_recipe_index', methods: ['GET'])]
    public function index(RecipeRepository $recipes, Request $request): Response
    {
        // -- filtres simples depuis l’URL
        $q          = trim((string) $request->query->get('q', ''));
        $category   = $request->query->get('category');
        $difficulty = $request->query->get('difficulty');

        // - tri très simple (par défaut: plus récentes)
        $sort = $request->query->get('sort', 'newest');
        $sortField = $sort === 'title' ? 'title' : 'createdAt';
        $sortDir   = $sort === 'oldest' ? 'ASC' : 'DESC';

        // - pagination naïve (OK pour un projet étudiant)
        $page     = max(1, (int) $request->query->get('p', 1));
        $pageSize = min(30, max(6, (int) ($request->query->get('ps', $request->query->get('per_page', 9)))));


        // - critères de base
        $criteria = ['isPublic' => true];
        if ($category)   { $criteria['category']   = $category; }
        if ($difficulty) { $criteria['difficulty'] = $difficulty; }

        // - si pas de recherche texte : on fait simple avec findBy()
        if ($q === '') {
            $all = $recipes->findBy($criteria, [$sortField => $sortDir]);
        } else {
            // - si recherche: un petit QueryBuilder (LIKE sur titre + description)
            $qb = $recipes->createQueryBuilder('r')
                ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
                ->andWhere('(LOWER(r.title) LIKE :q OR LOWER(r.description) LIKE :q)')
                ->setParameter('q', '%'.mb_strtolower($q).'%')
                ->orderBy('r.'.$sortField, $sortDir);

            if ($category)   { $qb->andWhere('r.category = :cat')->setParameter('cat', $category); }
            if ($difficulty) { $qb->andWhere('r.difficulty = :dif')->setParameter('dif', $difficulty); }

            $all = $qb->getQuery()->getResult();
        }

        // -- pagination en PHP (simple et lisible)
        $total   = count($all);
        $offset  = ($page - 1) * $pageSize;
        $items   = array_slice($all, $offset, $pageSize);

        return $this->render('recipe/index.html.twig', [
            'recipes'            => $items,
            'current_category'   => $category,
            'current_difficulty' => $difficulty,
            'current_search'     => $q,
            'sort'               => $sort,
            'page'               => $page,
            'page_size'          => $pageSize,
            'total'              => $total,
            'total_pages'        => (int) ceil(max(1, $total) / $pageSize),
        ]);
    }

    /** Création d’une recette (image optionnelle) */
    #[Route('/nouvelle', name: 'app_recipe_new', methods: ['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        Notification $notification
    ): Response {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // auteur = utilisateur connecté
            $recipe->setAuthor($this->getUser());

            // upload image (simple, sans suppression d’ancienne image)
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $original = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original);
                $newName  = $safe.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('recipes_directory'), $newName);
                    $recipe->setImage($newName);
                } catch (FileException) {
                    $this->addFlash('error', "Erreur lors de l\'upload de l'image.");
                }
            }

            $em->persist($recipe);
            $em->flush();

            // petit mail si la recette est publique direct
            if ($recipe->isPublic() && $recipe->getAuthor()?->getEmail()) {
                try {
                    $notification->sendTemplate(
                        to: $recipe->getAuthor()->getEmail(),
                        subject: '🍊 Votre recette est publiée',
                        htmlTemplate: 'email/recipe_published.html.twig',
                        context: [
                            'userName' => method_exists($recipe->getAuthor(), 'getFirstName') && $recipe->getAuthor()->getFirstName()
                                ? $recipe->getAuthor()->getFirstName()
                                : $recipe->getAuthor()->getEmail(),
                            'title' => $recipe->getTitle(),
                            'url'   => $this->generateUrl('app_recipe_show', ['id' => $recipe->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                            'subject' => '🍊 Votre recette est publiée',
                        ],
                        textTemplate: 'email/recipe_published.txt.twig'
                    );
                } catch (\Throwable) {
                    // on n’arrête pas la création si le mail plante
                }
            }

            $this->addFlash('success', '🍊 Recette créée avec succès !');
            return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
        }

        return $this->render('recipe/new.html.twig', [
            'recipe' => $recipe,
            'form'   => $form,
        ]);
    }

    /** Affichage d’une recette + vues + commentaires + avis */
    #[Route('/{id}', name: 'app_recipe_show', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
    public function show(
        Recipe $recipe,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviews
    ): Response {
        // compteur de vues
        $recipe->incrementViews();
        $em->flush();

        // form commentaire (sur la même page)
        $comment = new RecipeComment();
        $commentForm = $this->createForm(RecipeCommentType::class, $comment);
        $commentForm->handleRequest($request);
        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
            $comment->setRecipe($recipe);
            $comment->setAuthor($this->getUser());
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire ajouté !');
            return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
        }

        // avis : si l’utilisateur a déjà noté, on désactive le form
        $already = null;
        if ($this->getUser()) {
            $already = $reviews->findOneBy(['recipe' => $recipe, 'author' => $this->getUser()]);
        }
        $formReview = $this->createForm(ReviewType::class, new Review(), [
            'action'   => $this->generateUrl('app_review_new', ['id' => $recipe->getId()]),
            'method'   => 'POST',
            'disabled' => (bool) $already,
        ]);

        // stats “avis approuvés”
        $stats        = $reviews->getApprovedStats($recipe);
        $avgApproved  = round($stats['avg'], 1);
        $countApproved= $stats['count'];

        // favori ?
        $isFav = false;
        if ($this->getUser()) {
            $isFav = (bool) $em->getRepository(Favorite::class)->findOneBy([
                'recipe' => $recipe,
                'user'   => $this->getUser(),
            ]);
        }

        // ingredients / steps depuis des champs texte (ligne par ligne)
        $ingredients = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($recipe->getIngredients() ?? '')))));
        $steps       = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($recipe->getInstructions() ?? '')))));

        return $this->render('recipe/show.html.twig', [
            'recipe'        => $recipe,
            'commentForm'   => $commentForm,
            'form_review'   => $formReview->createView(),
            'alreadyRated'  => (bool) $already,
            'avgApproved'   => $avgApproved,
            'countApproved' => $countApproved,
            'isFav'         => $isFav,
            'ingredients'   => $ingredients,
            'steps'         => $steps,
            'comments'      => $em->getRepository(RecipeComment::class)->findBy(
                ['recipe' => $recipe],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    /** Petite route pour “noter” (1..5) */
    #[Route('/{id}/noter', name: 'app_recipe_rate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rate(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Note invalide.');
            return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
        }

        $existing = $em->getRepository(RecipeRating::class)->findOneBy([
            'recipe' => $recipe,
            'user'   => $this->getUser(),
        ]);

        if ($existing) {
            $existing->setRating($rating);
        } else {
            $new = new RecipeRating();
            $new->setRecipe($recipe);
            $new->setUser($this->getUser());
            $new->setRating($rating);
            $em->persist($new);
        }

        $em->flush();
        $this->addFlash('success', 'Note enregistrée !');

        return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
    }

    /** Édition d’une recette */
    #[Route('/{id}/modifier', name: 'app_recipe_edit', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        Notification $notification
    ): Response {
        // sécurité simple : auteur ou admin
        if ($recipe->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $wasPublic = $recipe->isPublic();

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // re-upload image si nouvelle image choisie
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $original = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original);
                $newName  = $safe.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('recipes_directory'), $newName);
                    $recipe->setImage($newName);
                } catch (FileException) {
                    $this->addFlash('error', "Erreur lors de l'upload de l'image.");
                }
            }

            $em->flush();

            // si on vient de passer privée -> publique : petit mail
            if (!$wasPublic && $recipe->isPublic() && $recipe->getAuthor()?->getEmail()) {
                try {
                    $notification->sendTemplate(
                        to: $recipe->getAuthor()->getEmail(),
                        subject: '🍊 Votre recette est publiée',
                        htmlTemplate: 'email/recipe_published.html.twig',
                        context: [
                            'userName' => method_exists($recipe->getAuthor(), 'getFirstName') && $recipe->getAuthor()->getFirstName()
                                ? $recipe->getAuthor()->getFirstName()
                                : $recipe->getAuthor()->getEmail(),
                            'title' => $recipe->getTitle(),
                            'url'   => $this->generateUrl('app_recipe_show', ['id' => $recipe->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                            'subject' => '🍊 Votre recette est publiée',
                        ],
                        textTemplate: 'email/recipe_published.txt.twig'
                    );
                    $this->addFlash('success', 'Recette modifiée (e-mail envoyé).');
                } catch (\Throwable) {
                    $this->addFlash('warning', "Recette modifiée, mais e-mail non envoyé.");
                }
            } else {
                $this->addFlash('success', 'Recette modifiée avec succès !');
            }

            return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form'   => $form,
        ]);
    }

    /** Suppression */
    #[Route('/{id}/supprimer', name: 'app_recipe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($recipe->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($recipe);
            $em->flush();
            $this->addFlash('success', 'Recette supprimée.');
        }

        return $this->redirectToRoute('app_recipe_index');
    }

    /** Mes recettes */
    #[Route('/mes-recettes', name: 'app_my_recipes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myRecipes(RecipeRepository $recipes, Request $request): Response
    {
        $sort = $request->query->get('sort', 'newest');
        $sortField = $sort === 'title' ? 'title' : 'createdAt';
        $sortDir   = $sort === 'oldest' ? 'ASC' : 'DESC';

        $page     = max(1, (int) $request->query->get('p', 1));
        $pageSize = min(30, max(6, (int) ($request->query->get('ps', $request->query->get('per_page', 9)))));


        $all = $recipes->findBy(['author' => $this->getUser()], [$sortField => $sortDir]);

        $total   = count($all);
        $offset  = ($page - 1) * $pageSize;
        $items   = array_slice($all, $offset, $pageSize);

        return $this->render('recipe/my_recipes.html.twig', [
            'recipes'     => $items,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total'       => $total,
            'total_pages' => (int) ceil(max(1, $total) / $pageSize),
            'sort'        => $sort,
        ]);
    }
}
