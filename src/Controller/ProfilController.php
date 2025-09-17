<?php

namespace App\Controller;

use App\Form\ProfilType;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')] // tout ici nécessite d’être connecté
final class ProfilController extends AbstractController
{
    /** Limite d’upload avatar: 2 Mo */
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    /** Types de fichiers acceptés */
    private const AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Nom du fichier “par défaut” à ne jamais supprimer */
    private const DEFAULT_AVATAR = 'default.png';

    #[Route('/mon-compte', name: 'app_profil', methods: ['GET'])]
    public function index(): Response
    {
        // Affiche la page “Mon compte”
        return $this->render('profil/index.html.twig');
    }

    #[Route('/mon-compte/modifier-mdp', name: 'app_change_password', methods: ['GET','POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(ChangePasswordFormType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user) {
                // Sécurité: ne devrait pas arriver grâce à IsGranted
                throw $this->createAccessDeniedException();
            }

            // Si le form a un champ “currentPassword”, on le vérifie
            if ($form->has('currentPassword')) {
                $current = $form->get('currentPassword')->getData();
                if (!$passwordHasher->isPasswordValid($user, $current)) {
                    $this->addFlash('danger', 'Ancien mot de passe incorrect.');
                    return $this->redirectToRoute('app_change_password');
                }
            }

            // Hash + sauvegarde du nouveau mot de passe
            $newPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();

            $this->addFlash('success', '✅ Mot de passe mis à jour avec succès.');
            return $this->redirectToRoute('app_profil');
        }

        return $this->render('profil/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/mon-compte/edit', name: 'app_profil_edit', methods: ['GET','POST'])]
    public function edit(
        Request $request,
        SluggerInterface $slugger,
        Filesystem $fs,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $oldAvatar = (string) $user?->getAvatar(); // on garde le nom actuel

        $form = $this->createForm(ProfilType::class, $user)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                // 1) gardes-fous: type + taille
                if (!\in_array($avatarFile->getMimeType(), self::AVATAR_MIME_TYPES, true)) {
                    $this->addFlash('danger', "Format d'image non pris en charge (JPEG, PNG, WEBP).");
                    return $this->redirectToRoute('app_profil_edit');
                }
                if ($avatarFile->getSize() > self::AVATAR_MAX_BYTES) {
                    $this->addFlash('danger', "Image trop lourde (max 2 Mo).");
                    return $this->redirectToRoute('app_profil_edit');
                }

                // 2) nom de fichier “safe” + unique
                $original = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original)->lower();
                $ext      = $avatarFile->guessExtension() ?: 'bin';
                $uniq     = substr(bin2hex(random_bytes(6)), 0, 12);
                $newName  = sprintf('%s-%s.%s', $safe, $uniq, $ext);

                try {
                    // 3) déplacement + mise à jour en BDD
                    $destDir = $this->getParameter('avatars_directory');
                    $avatarFile->move($destDir, $newName);
                    $user->setAvatar($newName);

                    // 4) nettoyage: on supprime l’ancien si ce n’est pas le “par défaut”
                    if ($oldAvatar && $oldAvatar !== self::DEFAULT_AVATAR && $oldAvatar !== $newName) {
                        $oldPath = rtrim($destDir, '/').'/'.$oldAvatar;
                        if (\is_file($oldPath)) {
                            $fs->remove($oldPath);
                        }
                    }
                } catch (FileException) {
                    $this->addFlash('danger', 'Erreur lors de l’upload du fichier.');
                    return $this->redirectToRoute('app_profil_edit');
                }
            }

            // 5) flush des éventuels autres champs modifiés
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profil');
        }

        return $this->render('profil/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
