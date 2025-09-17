<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use App\Security\Voter\ReviewVoter;
use App\Service\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReviewController extends AbstractController
{
    #[Route('/recipes/{id<\d+>}/review', name: 'app_review_new', methods: ['GET','POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(
        #[MapEntity(expr: 'repository.find(id)')] ?Recipe $recipe,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$recipe) {
            throw $this->createNotFoundException('Recette introuvable.');
        }

        // Empêcher un 2ᵉ avis par la même personne (en plus de UniqueEntity)
        $existing = $em->getRepository(Review::class)->findOneBy([
            'recipe' => $recipe,
            'author' => $this->getUser(),
        ]);
        if ($existing) {
            $this->addFlash('danger', 'Vous avez déjà noté cette recette.');
            return $this->redirectToRoute('app_recipe_show', [
                'id' => $recipe->getId(),
                '_fragment' => 'reviews',
            ]);
        }

        // Interdire de noter sa propre recette
        if ($recipe->getAuthor() === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas noter votre propre recette.');
            return $this->redirectToRoute('app_recipe_show', [
                'id' => $recipe->getId(),
                '_fragment' => 'reviews',
            ]);
        }

        $review = (new Review())
            ->setRecipe($recipe)
            ->setAuthor($this->getUser())
            ->setIsApproved($this->isGranted('ROLE_ADMIN'));


        // Un non-admin → avis en modération
        if (!$this->isGranted('ROLE_ADMIN')) {
            $review->setIsApproved(false);
        }

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em->persist($review);
                $em->flush();
                $this->addFlash('success', 'Merci pour votre avis !');
            } else {
                $this->addFlash('error', 'Impossible d’enregistrer votre avis. Vérifiez la note et/ou le commentaire.');
            }

            return $this->redirectToRoute('app_recipe_show', [
                'id'        => $recipe->getId(),
                '_fragment' => 'reviews',
            ]);
        }

        // Le formulaire est déjà rendu sur la page recette → on renvoie dessus.
        return $this->redirectToRoute('app_recipe_show', [
            'id'        => $recipe->getId(),
            '_fragment' => 'reviews',
        ]);
    }

    #[Route('/avis/{id<\d+>}/editer', name: 'app_review_edit', methods: ['GET','POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        // ➜ Voter : auteur OU admin
        $this->denyAccessUnlessGranted(ReviewVoter::EDIT, $review);

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Un user qui modifie ⇒ avis remis en attente
            if (!$this->isGranted('ROLE_ADMIN')) {
                $review->setIsApproved(false);
            }
            $em->flush();

            $this->addFlash('success', $this->isGranted('ROLE_ADMIN')
                ? 'Avis mis à jour.'
                : 'Avis mis à jour et renvoyé en modération.'
            );

            return $this->redirectToRoute('app_recipe_show', [
                'id' => $review->getRecipe()->getId(),
                '_fragment' => 'reviews',
            ]);
        }

        return $this->render('admin/review/edit.html.twig', [
            'form'   => $form->createView(),
            'review' => $review,
        ]);
    }

    #[Route('/avis/{id<\d+>}/supprimer', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        // ➜ Voter : auteur OU admin
        $this->denyAccessUnlessGranted(ReviewVoter::DELETE, $review);

        if ($this->isCsrfTokenValid('delete_review_'.$review->getId(), (string) $request->request->get('_token'))) {
            $em->remove($review);
            $em->flush();
            $this->addFlash('success', 'Avis supprimé.');
        }

        return $this->redirectToRoute('app_recipe_show', [
            'id' => $review->getRecipe()->getId(),
            '_fragment' => 'reviews',
        ]);
    }

    // ----------- MODÉRATION ADMIN -----------

    #[Route('/admin/avis', name: 'app_admin_reviews', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminList(ReviewRepository $repo, Request $request): Response
    {
        $status = in_array($request->query->get('status', 'pending'), ['pending','approved','all'], true)
            ? $request->query->get('status', 'pending')
            : 'pending';

        $page     = max(1, (int) $request->query->get('p', 1));
        $pageSize = 15;

        $qb = $repo->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if ($status === 'pending')   { $qb->andWhere('r.isApproved = 0'); }
        if ($status === 'approved')  { $qb->andWhere('r.isApproved = 1'); }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $reviews = $qb->setFirstResult(($page-1)*$pageSize)->setMaxResults($pageSize)->getQuery()->getResult();

        return $this->render('admin/review/index.html.twig', [
            'reviews'     => $reviews,
            'status'      => $status,
            'page'        => $page,
            'total'       => $total,
            'total_pages' => max(1, (int)ceil($total/$pageSize)),
        ]);
    }


    // Route d’admin pour approuver un avis :
// - URL: /admin/avis/{id}/approve avec {id} numérique
// - Méthode HTTP: POST (on évite GET pour une action mutatrice)
// - Nom de route: app_admin_review_approve
    #[Route('/admin/avis/{id<\d+>}/approve', name: 'app_admin_review_approve', methods: ['POST'])]
// Sécurité : seulement un utilisateur avec le rôle ADMIN peut l’exécuter
    #[IsGranted('ROLE_ADMIN')]
    public function approve(
        Review $review,                   // ParamConverter: Symfony charge l’entité Review par son {id}
        Request $request,                 // Accès au POST (pour récupérer le jeton CSRF)
        EntityManagerInterface $em,       // Pour flush en base
        Notification $notification        // Service perso: envoi d’e-mails (templated)
    ): Response {

        // Protection CSRF : on vérifie que le jeton posté est valide.
        // Le tokenId doit correspondre à celui utilisé dans le formulaire d’approbation.
        if ($this->isCsrfTokenValid('approve_review_'.$review->getId(), (string) $request->request->get('_token'))) {

            // Marque l’avis comme “approuvé” et enregistre en base
            $review->setIsApproved(true);
            $em->flush();

            // Notification de l’auteur (si l’email existe).
            // Try/catch pour ne pas bloquer l’UX si l’envoi échoue.
            if ($review->getAuthor()?->getEmail()) {
                try {
                    $notification->sendTemplate(
                        to: $review->getAuthor()->getEmail(),
                        subject: '✅ Votre avis a été approuvé',
                        htmlTemplate: 'email/review_approved.html.twig',
                        context: [
                            'userName' => $review->getAuthor()->getPrenom() ?: $review->getAuthor()->getEmail(),
                            'recipe'   => $review->getRecipe(),
                            'subject'  => '✅ Votre avis a été approuvé',
                        ],
                        textTemplate: 'email/review_approved.txt.twig'
                    );
                } catch (\Throwable $e) {
                    // On ignore l’erreur d’envoi (ou on peut addFlash/loguer).
                }
            }

            // Message de succès pour l’admin
            $this->addFlash('success', 'Avis approuvé.');
        }

        // Quoi qu’il arrive, on revient à la liste d’administration des avis
        return $this->redirectToRoute('app_admin_reviews');
    }


    #[Route('/admin/avis/{id<\d+>}/reject', name: 'app_admin_review_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('reject_review_'.$review->getId(), (string) $request->request->get('_token'))) {
            $em->remove($review);
            $em->flush();
            $this->addFlash('success', 'Avis supprimé.');
        }

        return $this->redirectToRoute('app_admin_reviews');
    }
}
