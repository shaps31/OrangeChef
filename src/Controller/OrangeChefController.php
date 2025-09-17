<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Entity\Recipe;
use App\Service\Notification;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class OrangeChefController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(RecipeRepository $recipes, UserRepository $users, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $latest = $recipes->createQueryBuilder('r')
            ->andWhere('r.isPublic = :pub')->setParameter('pub', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()->getResult();

        $recipesCount = (int) $em->getRepository(\App\Entity\Recipe::class)->count(['isPublic' => true]);
        $usersCount   = (int) $em->getRepository(\App\Entity\User::class)->count([]);
        $reviewsCount = (int) $em->getRepository(\App\Entity\Review::class)->count(['isApproved' => true]);

        return $this->render('orange_chef/index.html.twig', [
            'latestRecipes' => $latest,
            'recipesCount'  => $recipesCount,
            'usersCount'    => $usersCount,
            'reviewsCount'  => $reviewsCount,
        ]);
    }

    #[Route('/fixe/{abc<\d+>}', name: 'app_orange_chef')]
    public function index(string $abc, Request $request): Response
    {
        $parametre = $request->query->get('parametre');
        return $this->render('orange_chef/index.html.twig', [
            'controller_name' => 'OrangeChefController',
            'parametre' => $parametre,
            'abc' => $abc
        ]);
    }



    #[Route('/recettes-oc', name: 'recettes')]
    public function recettes(): Response
    {
        return $this->redirectToRoute('app_recipe_index');
    }

    #[Route('/mail-demo', name: 'app_mail_demo')]
    public function mailDemo(Notification $notification): Response
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }
        $notification->sendTemplate(
            to: 'test@local.test',
            subject: '🍊 OrangeChef • Démo personnalisée',
            htmlTemplate: 'email/orange_demo.html.twig',        // ✅ bon chemin
            context: [
                'userName' => 'Shabadine',
                'abc' => '123',
                'parametre' => 'coucou',
                'ctaUrl' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'subject' => '🍊 OrangeChef • Démo personnalisée',
            ],
            textTemplate: 'email/orange_demo.txt.twig'          // ✅ bon chemin
        );

        return new Response('Mail personnalisé envoyé.');
    }
    #[Route('/_preview/email/orange-demo', name: 'preview_orange_demo')]
    public function previewOrangeDemo(): Response
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createNotFoundException();
        }
        return $this->render('email/orange_demo.html.twig', [
            'userName'  => 'Shabadine',
            'abc'       => '123',
            'parametre' => 'coucou',
            'ctaUrl'    => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'subject'   => '🍊 OrangeChef • Démo personnalisée',
        ]);
    }


    #[Route('/envoyer-notification', name: 'app_envoyer_notification')]
    public function envoyerNotification(Request $request, Notification $notification): Response
    {
        $message = null;

        if ($request->isMethod('POST')) {
            $abc = $request->request->get('abc', 'non précisé');
            $parametre = $request->request->get('parametre', 'non précisé');

            try {
                $notification->sendTemplate(
                    to: 'destinataire@domaine.ext',
                    subject: 'Requête reçue',
                    htmlTemplate: 'email/notification_formulaire.html.twig',
                    context: [
                        'abc' => $abc,
                        'parameter' => $parametre,
                        'subject' => 'Requête reçue',
                    ],
                    textTemplate: 'email/notification_formulaire.txt.twig'
                );
                $message = 'E-mail envoyé avec succès.';
            } catch (\Throwable $e) {
                $message = 'Échec envoi : ' . $e->getMessage();
            }
        }

        return $this->render('orange_chef/envoyer_notification.html.twig', [
            'message' => $message ?? null,
        ]);
    }
}
