<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\RecipeComment;
use App\Entity\RecipeRating;
use App\Form\RecipeType;
use App\Form\RecipeCommentType;
use App\Form\ReviewType;
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
use App\Repository\ReviewRepository;
use App\Repository\RecipeRepository;
use App\Entity\Review;
use App\Entity\Favorite;

#[Route('/recettes')]
class RecipeController extends AbstractController
{
    #[Route('/', name: 'app_recipe_index', methods: ['GET'])]
    public function index(\App\Repository\RecipeRepository $repo, Request $request): Response
    {
        // --- filtres & recherche
        $q          = trim((string) ($request->query->get('q', $request->query->get('search'))));
        $category   = $request->query->get('category');
        $difficulty = $request->query->get('difficulty');
        $variety    = $request->query->get('variety', $request->query->get('variete'));

        // alias variety -> category
        if (!$category && $variety) {
            $category = $variety;
        }

        $page     = max(1, (int) $request->query->get('p', 1));
        $pageSize = min(30, max(6, (int) $request->query->get('ps', 9)));
        $sort     = $request->query->get('sort', 'newest');

        $allowedSorts = [
            'newest' => ['r.createdAt', 'DESC'],
            'oldest' => ['r.createdAt', 'ASC'],
            'title'  => ['r.title', 'ASC'],
        ];
        if (!isset($allowedSorts[$sort])) {
            $sort = 'newest';
        }

        // --- construction de la requête
        $qb = $repo->createQueryBuilder('r')
            ->where('r.isPublic = :public')
            ->setParameter('public', true)
            ->orderBy($allowedSorts[$sort][0], $allowedSorts[$sort][1]);

        if ($category) {
            $qb->andWhere('r.category = :category')
                ->setParameter('category', $category);
        }

        if ($difficulty) {
            $qb->andWhere('r.difficulty = :difficulty')
                ->setParameter('difficulty', $difficulty);
        }

        if ($q !== '') {
            $qb->andWhere(
                'LOWER(r.title)       LIKE :q
             OR LOWER(r.description) LIKE :q
             OR LOWER(r.ingredients) LIKE :q
             OR LOWER(r.category)    LIKE :q'
            )->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        // --- pagination
        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('orderBy')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return $this->render('recipe/index.html.twig', [
            'recipes'            => $items,     // <-- la liste (corrige l’ancienne variable)
            'current_category'   => $category,
            'current_difficulty' => $difficulty,
            'current_search'     => $q,
            'page'               => $page,
            'page_size'          => $pageSize,
            'total'              => $total,
            'total_pages'        => (int) ceil(max(1, $total) / $pageSize),
            'sort'               => $sort,
        ]);
    }


    #[Route('/nouvelle', name: 'app_recipe_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Notification $notification
    ): Response {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recipe->setAuthor($this->getUser());

            // Upload image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('recipes_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }

                $recipe->setImage($newFilename);
            }

            $entityManager->persist($recipe);
            $entityManager->flush();

            // 👉 Envoi d'email si la recette est déjà publique à la création
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
                } catch (\Throwable $e) {
                    $this->addFlash('warning', "Recette créée, mais l'e-mail n'a pas pu être envoyé : ".$e->getMessage());
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

    #[Route('/{id}', name: 'app_recipe_show', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
    public function show(
        Recipe $recipe,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviews
    ): Response {
        // 1) Compteur de vues
        $recipe->incrementViews();
        $em->flush();

        // 2) Formulaire de commentaire (POST sur la même page)
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

        // 3) Formulaire d’avis (désactivé si déjà noté)
        $already = null;
        if ($this->getUser()) {
            $already = $reviews->findOneBy([
                'recipe' => $recipe,
                'author' => $this->getUser(),
            ]);
        }

        $formReview = $this->createForm(ReviewType::class, new Review(), [
            'action'   => $this->generateUrl('app_review_new', ['id' => $recipe->getId()]),
            'method'   => 'POST',
            'disabled' => (bool) $already, // bloque si déjà noté
        ]);
        // ⬇ stats sur avis approuvés
        $stats = $reviews->getApprovedStats($recipe);
        $avgApproved   = round($stats['avg'], 1);
        $countApproved = $stats['count'];

        $isFav = false;
        if ($this->getUser()) {
            $isFav = (bool) $em->getRepository(Favorite::class)
                ->findOneBy(['recipe' => $recipe, 'user' => $this->getUser()]);
        }
        $ingredients = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($recipe->getIngredients() ?? '')))));
        $steps       = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($recipe->getInstructions() ?? '')))));

        // 4) Render
        return $this->render('recipe/show.html.twig', [
            'recipe'       => $recipe,
            'commentForm'  => $commentForm,              // {{ form_start(commentForm) }} ...
            'form_review'  => $formReview->createView(), // {{ form_start(form_review) }} ...
            'alreadyRated' => (bool) $already,
            'avgApproved'  => $avgApproved,
            'countApproved'=> $countApproved,
            'isFav'        => $isFav,
            'ingredients'   => $ingredients,
            'steps'         => $steps,
            'comments'     => $em->getRepository(RecipeComment::class)
                ->findBy(['recipe' => $recipe], ['createdAt' => 'DESC']),

        ]);
    }

    #[Route('/{id}/noter', name: 'app_recipe_rate', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rate(Recipe $recipe, Request $request, EntityManagerInterface $entityManager): Response
    {
        $rating = (int) $request->request->get('rating');

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Note invalide');
            return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
        }

        // Vérifier si l'utilisateur a déjà noté
        $existingRating = $entityManager->getRepository(RecipeRating::class)
            ->findOneBy(['recipe' => $recipe, 'user' => $this->getUser()]);

        if ($existingRating) {
            $existingRating->setRating($rating);
        } else {
            $newRating = new RecipeRating();
            $newRating->setRecipe($recipe);
            $newRating->setUser($this->getUser());
            $newRating->setRating($rating);
            $entityManager->persist($newRating);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Note enregistrée !');
        return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
    }

    #[Route('/{id}/modifier', name: 'app_recipe_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Notification $notification
    ): Response {
        // Vérifier que l'utilisateur est l'auteur
        if ($recipe->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $wasPublic = $recipe->isPublic();

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('recipes_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }

                $recipe->setImage($newFilename);
            }

            $entityManager->flush();

            // 👉 Envoi d'email si on vient de passer privé → public
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
                    $this->addFlash('success', 'Recette modifiée et e-mail de publication envoyé.');
                } catch (\Throwable $e) {
                    $this->addFlash('warning', "Recette modifiée, mais l'e-mail n'a pas pu être envoyé : ".$e->getMessage());
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

    #[Route('/{id}/supprimer', name: 'app_recipe_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est l'auteur
        if ($recipe->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($recipe);
            $entityManager->flush();
            $this->addFlash('success', 'Recette supprimée');
        }

        return $this->redirectToRoute('app_recipe_index');
    }

    #[Route('/mes-recettes', name: 'app_my_recipes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myRecipes(EntityManagerInterface $entityManager, Request $request): Response
    {
        $page     = max(1, (int) $request->query->get('p', 1));
        $pageSize = min(30, max(6, (int) $request->query->get('ps', 9)));
        $sort     = $request->query->get('sort', 'newest');
        $allowedSorts = [
            'newest' => ['r.createdAt', 'DESC'],
            'oldest' => ['r.createdAt', 'ASC'],
            'title'  => ['r.title', 'ASC'],
        ];
        if (!array_key_exists($sort, $allowedSorts)) {
            $sort = 'newest';
        }

        $qb = $entityManager->getRepository(Recipe::class)
            ->createQueryBuilder('r')
            ->where('r.author = :author')
            ->setParameter('author', $this->getUser())
            ->orderBy($allowedSorts[$sort][0], $allowedSorts[$sort][1]);

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy');
        $total = (int) $countQb
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $recipes = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return $this->render('recipe/my_recipes.html.twig', [
            'recipes'     => $recipes,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total'       => $total,
            'total_pages' => (int) ceil(max(1, $total) / $pageSize),
            'sort'        => $sort,
        ]);
    }
}
