<?php

namespace App\Controller;

use App\Form\ProfilType;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')] // ➜ toutes les actions nécessitent un utilisateur connecté
final class ProfilController extends AbstractController
{
    /** Limite de taille du fichier avatar (2 Mo) */
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    /** Types d’images autorisés pour l’avatar */
    private const AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    #[Route('/mon-compte', name: 'app_profil', methods: ['GET'])]
    public function index(): Response
    {
        // Affiche simplement la page "Mon compte"
        return $this->render('profil/index.html.twig');
    }

    #[Route('/mon-compte/modifier-mdp', name: 'app_change_password', methods: ['GET','POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        // 1) Crée et traite le formulaire
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        // 2) Si soumis et valide, on met à jour le mot de passe
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user) {
                // Par sécurité (devrait déjà être bloqué par l'attribut IsGranted)
                throw $this->createAccessDeniedException();
            }

            // (Optionnel) si le form contient "currentPassword", on le vérifie
            if ($form->has('currentPassword')) {
                $current = $form->get('currentPassword')->getData();
                if (!$passwordHasher->isPasswordValid($user, $current)) {
                    $this->addFlash('danger', 'Ancien mot de passe incorrect.');
                    return $this->redirectToRoute('app_change_password');
                }
            }

            // 3) Hash + enregistrement
            $newPassword   = $form->get('plainPassword')->getData();
            $hashed        = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashed);
            $em->flush();

            $this->addFlash('success', '✅ Mot de passe mis à jour avec succès.');
            return $this->redirectToRoute('app_profil');
        }

        // 4) Affiche le formulaire (GET initial ou erreurs)
        return $this->render('profil/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mon-compte/edit', name: 'app_profil_edit', methods: ['GET','POST'])]
    public function edit(
        Request $request,
        \Symfony\Component\String\Slugger\SluggerInterface $slugger,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        // 1) Crée et traite le formulaire profil
        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        // 2) Si soumis et valide, on gère l’avatar (si fourni) puis on sauvegarde
        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                // — Vérifications simples du fichier —
                if (!in_array($avatarFile->getMimeType(), self::AVATAR_MIME_TYPES, true)) {
                    $this->addFlash('danger', "Format d'image non pris en charge (JPEG, PNG, WEBP).");
                    return $this->redirectToRoute('app_profil_edit');
                }
                if ($avatarFile->getSize() > self::AVATAR_MAX_BYTES) {
                    $this->addFlash('danger', "Image trop lourde (max 2 Mo).");
                    return $this->redirectToRoute('app_profil_edit');
                }

                // — Création d’un nom de fichier sûr —
                $original = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original);                 // ex: "ma-super-photo"
                $ext      = $avatarFile->guessExtension() ?: 'bin';    // ex: "jpg"
                $uniq     = substr(bin2hex(random_bytes(6)), 0, 12);   // petit identifiant unique
                $newName  = sprintf('%s-%s.%s', $safe, $uniq, $ext);   // "ma-super-photo-a1b2c3d4e5f6.jpg"

                // — Déplacement du fichier + maj de l’utilisateur —
                try {
                    $avatarFile->move($this->getParameter('avatars_directory'), $newName);
                    $user->setAvatar($newName);
                } catch (FileException) {
                    $this->addFlash('danger', 'Erreur lors de l’upload du fichier.');
                    return $this->redirectToRoute('app_profil_edit');
                }
            }

            // 3) On persiste les autres champs modifiés (email, prénom, etc.)
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profil');
        }

        // 4) Affiche le formulaire
        return $this->render('profil/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

