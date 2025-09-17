<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use App\Repository\ReviewRepository;
use App\Service\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OrangeChefController extends AbstractController
{
    /**
     * Page d’accueil : dernières recettes publiques + compteurs simples.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(
        RecipeRepository $recipes,
        UserRepository $users,
        ReviewRepository $reviews
    ): Response {
        // 1) Dernières recettes publiques (ex: 3)
        //    ➜ nécessite une méthode custom dans RecipeRepository: findLatestPublic(int $limit)
        $latest = $recipes->findLatestPublic(3);

        // 2) Compteurs pour les tuiles (recettes publiques / utilisateurs / avis approuvés)
        //    ➜ nécessite: countPublic() côté RecipeRepository et countApproved() côté ReviewRepository
        $recipesCount = $recipes->countPublic();
        $usersCount   = $users->count([]);         // compteur natif: SELECT COUNT(*) FROM user
        $reviewsCount = $reviews->countApproved(); // méthode custom dans ReviewRepository

        // 3) Rendu
        return $this->render('orange_chef/index.html.twig', [
            'latestRecipes' => $latest,
            'recipesCount'  => $recipesCount,
            'usersCount'    => $usersCount,
            'reviewsCount'  => $reviewsCount,
        ]);
    }

    /**
     * Route “fixe” d’exemple avec un paramètre numérique.
     * URL: /fixe/123?parametre=hello
     */
    #[Route('/fixe/{abc<\d+>}', name: 'app_orange_chef', methods: ['GET'])]
    public function index(string $abc, Request $request): Response
    {
        $parametre = $request->query->get('parametre'); // ?parametre=...
        return $this->render('orange_chef/index.html.twig', [
            'controller_name' => 'OrangeChefController',
            'parametre'       => $parametre,
            'abc'             => $abc,
        ]);
    }

    /**
     * Redirection simple vers la vraie liste des recettes.
     */
    #[Route('/recettes-oc', name: 'recettes', methods: ['GET'])]
    public function recettes(): Response
    {
        return $this->redirectToRoute('app_recipe_index');
    }

    /**
     * Démo d’envoi d’email basé sur un template Twig (DEV uniquement).
     */
    #[Route('/mail-demo', name: 'app_mail_demo', methods: ['GET'])]
    public function mailDemo(Notification $notification): Response
    {
        // sécurité: uniquement en environnement dev
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        $notification->sendTemplate(
            to: 'test@local.test',
            subject: '🍊 OrangeChef • Démo personnalisée',
            htmlTemplate: 'email/orange_demo.html.twig',
            context: [
                'userName'  => 'Shabadine',
                'abc'       => '123',
                'parametre' => 'coucou',
                'ctaUrl'    => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'subject'   => '🍊 OrangeChef • Démo personnalisée',
            ],
            textTemplate: 'email/orange_demo.txt.twig'
        );

        return new Response('Mail personnalisé envoyé.');
    }

    /**
     * Aperçu du template email dans le navigateur (DEV uniquement).
     */
    #[Route('/_preview/email/orange-demo', name: 'preview_orange_demo', methods: ['GET'])]
    public function previewOrangeDemo(): Response
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }

        return $this->render('email/orange_demo.html.twig', [
            'userName'  => 'Shabadine',
            'abc'       => '123',
            'parametre' => 'coucou',
            'ctaUrl'    => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'subject'   => '🍊 OrangeChef • Démo personnalisée',
        ]);
    }

    /**
     * Petit formulaire qui envoie un email (GET affiche / POST envoie).
     * Adresse de destination lue depuis config: parameter "app.notification_to".
     */
    #[Route('/envoyer-notification', name: 'app_envoyer_notification', methods: ['GET','POST'])]
    public function envoyerNotification(Request $request, Notification $notification): Response
    {
        $message = null;
        $to = (string) $this->getParameter('app.notification_to');

        if ($request->isMethod('POST')) {
            // On récupère les champs postés, avec valeurs par défaut si manquants
            $abc       = (string) $request->request->get('abc', 'non précisé');
            $parametre = (string) $request->request->get('parametre', 'non précisé');

            try {
                $notification->sendTemplate(
                    to: $to,
                    subject: 'Requête reçue',
                    htmlTemplate: 'email/notification_formulaire.html.twig',
                    context: [
                        'abc'       => $abc,
                        'parametre' => $parametre,
                        'subject'   => 'Requête reçue',
                    ],
                    textTemplate: 'email/notification_formulaire.txt.twig'
                );
                $message = 'E-mail envoyé avec succès.';
            } catch (\Throwable $e) {
                // on garde le message simple (pour démo)
                $message = 'Échec envoi : ' . $e->getMessage();
            }
        }

        return $this->render('orange_chef/envoyer_notification.html.twig', [
            'message' => $message,
        ]);
    }
}
