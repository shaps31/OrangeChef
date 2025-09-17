<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FriendController extends AbstractController
{
    #[Route('/friends', name: 'app_friends')]
    public function index(Request $request, UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $session = $request->getSession();

        $sent = $session->get('friends_sent', []);
        $accepted = $session->get('friends_accepted', []);

        $users = $userRepo->findAll();

        return $this->render('friends/index.html.twig', [
            'users' => $users,
            'sent' => $sent,
            'accepted' => $accepted,
            'me' => $user,
        ]);
    }

    #[Route('/friends/add/{id}', name: 'app_friends_add', methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $session = $request->getSession();
        $sent = $session->get('friends_sent', []);
        if (!in_array($id, $sent, true)) {
            $sent[] = $id;
        }
        $session->set('friends_sent', $sent);
        $this->addFlash('success', 'Demande d\'ami envoyée (démo).');

        return $this->redirectToRoute('app_friends');
    }

    #[Route('/friends/accept/{id}', name: 'app_friends_accept', methods: ['POST'])]
    public function accept(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $session = $request->getSession();
        $accepted = $session->get('friends_accepted', []);
        if (!in_array($id, $accepted, true)) {
            $accepted[] = $id;
        }
        $session->set('friends_accepted', $accepted);
        $this->addFlash('success', 'Ami ajouté (démo).');

        return $this->redirectToRoute('app_friends');
    }
}
