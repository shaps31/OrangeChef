<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\TranslatorInterface;

    #[Route('/mon-compte')]
    class MonCompteController extends AbstractController
    {
    #[Route('/edit', name: 'app_profil_edit')]
    public function modifierProfil(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, TranslatorInterface $translator): Response
    {
    /** @var User $user */
    $user = $this->getUser();
    $ancienAvatar = $user->getAvatar(); // 🔸 on garde l'ancien nom de fichier

    $form = $this->createForm(ProfilType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $avatarFile = $form->get('avatar')->getData();

        if ($avatarFile) {
            $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

            try {
                $avatarFile->move(
                    $this->getParameter('avatars_directory'),
                    $newFilename
                );

                // 🔥 Supprimer l’ancien avatar (s’il existe et pas par défaut)
                if ($ancienAvatar && $ancienAvatar !== 'default.png') {
                    $ancienChemin = $this->getParameter('avatars_directory') . '/' . $ancienAvatar;
                    if (file_exists($ancienChemin)) {
                        unlink($ancienChemin);
                    }
                }

                // Mettre à jour l’avatar
                if ($user->getAvatar()) {
                    $oldAvatarPath = $this->getParameter('avatars_directory') . '/' . $user->getAvatar();
                    if (file_exists($oldAvatarPath)) {
                        unlink($oldAvatarPath);
                    }
                }
                $user->setAvatar($newFilename);
            } catch (FileException $e) {
                $this->addFlash('danger', $this->translator->trans('❌ Erreur lors de l\'upload de l\'avatar.'));
            }
        }

        $em->flush();
        $this->addFlash('success', $this->translator->trans('✅ Profil mis à jour avec succès !'));
        return $this->redirectToRoute('app_profil');
    }

    return $this->render('profil/edit.html.twig', [
        'form' => $form->createView(),
    ]);
    }
}
