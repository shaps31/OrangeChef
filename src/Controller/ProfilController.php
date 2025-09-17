<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\ChangePasswordFormType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class ProfilController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_profil', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response {
        return $this->render('profil/index.html.twig');
    }
    #[Route('/mon-compte/modifier-mdp', name: 'app_change_password', methods: ['GET','POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user) {
                throw $this->createAccessDeniedException();
            }

            // (Optionnel) Vérifie l'ancien mot de passe si le champ existe
            if ($form->has('currentPassword')) {
                $current = $form->get('currentPassword')->getData();
                if (!$passwordHasher->isPasswordValid($user, $current)) {
                    $this->addFlash('danger', 'Ancien mot de passe incorrect.');
                    return $this->redirectToRoute('app_change_password', status: Response::HTTP_SEE_OTHER);
                }
            }

            $newPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $em->flush();

            $this->addFlash('success', '✅ Mot de passe mis à jour avec succès.');
            return $this->redirectToRoute('app_profil', status: Response::HTTP_SEE_OTHER);
        }


        return $this->render('profil/change_password.html.twig', [
        'form' => $form->createView(),
    ]);
}

    #[Route('/mon-compte/edit', name: 'app_profil_edit', methods: ['GET','POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, SluggerInterface $slugger, EntityManagerInterface $em): Response {
        $user = $this->getUser();

        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                // 1) Petits garde-fous
                $allowed = ['image/jpeg','image/png','image/webp'];
                if (!in_array($avatarFile->getMimeType(), $allowed, true)) {
                    $this->addFlash('danger', "Format d'image non pris en charge (JPEG, PNG, WEBP).");
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }
                if ($avatarFile->getSize() > 2 * 1024 * 1024) { // 2 Mo
                    $this->addFlash('danger', "Image trop lourde (max 2 Mo).");
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }

                // 2) Nom de fichier sûr et simple
                $original = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original);
                $ext      = $avatarFile->guessExtension() ?: 'bin';
                $uniq     = substr(bin2hex(random_bytes(6)), 0, 12);
                $newFilename = sprintf('%s-%s.%s', $safe, $uniq, $ext);

                // 3) Déplacer le fichier + enregistrer le nom
                try {
                    $avatarFile->move($this->getParameter('avatars_directory'), $newFilename);
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l’upload du fichier.');
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profil', status: Response::HTTP_SEE_OTHER);

        }

        return $this->render('profil/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
