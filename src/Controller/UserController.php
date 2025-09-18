<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;



#[IsGranted('ROLE_ADMIN')]
#[Route('/user', name: 'app_user_')]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $users->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('plainPassword')) {
                $plain = (string) $form->get('plainPassword')->getData();
                if ($plain !== '') {
                    $user->setPassword($hasher->hashPassword($user, $plain));
                }
            }
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', '✅ Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_user_index', ['_locale' => $request->getLocale()]);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET','POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('plainPassword')) {
                $plain = (string) $form->get('plainPassword')->getData();
                if ($plain !== '') {
                    $user->setPassword($hasher->hashPassword($user, $plain));
                }
            }
            $em->flush();
            $this->addFlash('success', '✅ Utilisateur mis à jour avec succès.');
            return $this->redirectToRoute('app_user_index', ['_locale' => $request->getLocale()]);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->getUser() === $user) {
            $this->addFlash('error', '❌ Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index', ['_locale' => $request->getLocale()]);
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', '🗑️ Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('app_user_index', ['_locale' => $request->getLocale()]);
    }
}
