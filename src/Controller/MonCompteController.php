<?php

namespace App\Controller;

use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/mon-compte')]
#[IsGranted('IS_AUTHENTICATED_FULLY')] // toutes les actions nécessitent une session valide
final class MonCompteController extends AbstractController
{
    /** 2 Mo */
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    /** Types autorisés */
    private const AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Nom de l’avatar “par défaut” à ne pas supprimer */
    private const DEFAULT_AVATAR = 'default.png';

    #[Route('/edit', name: 'app_profil_edit', methods: ['GET','POST'])]
    public function modifierProfil(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        TranslatorInterface $t,
        Filesystem $fs, // ➜ pour supprimer l’ancien fichier proprement
    ): Response {
        // 1) Sécurité ceinture+bretelles (IsGranted protège déjà)
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login', status: Response::HTTP_SEE_OTHER);
        }

        // On mémorise l’ancien nom de fichier pour éventuellement le supprimer après upload
        $ancienAvatar = (string) $user->getAvatar();

        // 2) Formulaire lié à l’utilisateur
        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        // 3) Traitement si OK
        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            // — Si un fichier a été envoyé —
            if ($avatarFile) {
                // a) Garde-fous : type MIME + taille
                if (!\in_array($avatarFile->getMimeType(), self::AVATAR_MIME_TYPES, true)) {
                    $this->addFlash('danger', $t->trans("Format d'image non pris en charge (JPEG, PNG, WEBP)."));
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }
                if ($avatarFile->getSize() > self::AVATAR_MAX_BYTES) {
                    $this->addFlash('danger', $t->trans('Image trop lourde (max 2 Mo).'));
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }

                // b) Nom de fichier “safe” + unique
                $original = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original)->lower();   // ex: "ma-super-photo"
                $ext      = $avatarFile->guessExtension() ?: 'bin';
                $uniq     = substr(bin2hex(random_bytes(6)), 0, 12);
                $newName  = sprintf('%s-%s.%s', $safe, $uniq, $ext);

                // c) Déplacement + enregistrement en base
                try {
                    $destDir = $this->getParameter('avatars_directory'); // config/services.yaml → parameters
                    $avatarFile->move($destDir, $newName);
                    $user->setAvatar($newName);

                    // d) Suppression de l’ancien fichier si pertinent
                    if ($ancienAvatar && $ancienAvatar !== self::DEFAULT_AVATAR && $ancienAvatar !== $newName) {
                        $oldPath = $destDir.'/'.$ancienAvatar;
                        if (\is_file($oldPath)) {
                            $fs->remove($oldPath);
                        }
                    }
                } catch (FileException) {
                    $this->addFlash('danger', $t->trans("❌ Erreur lors de l'upload de l'avatar."));
                    return $this->redirectToRoute('app_profil_edit', status: Response::HTTP_SEE_OTHER);
                }
            }

            // e) Sauvegarde des autres champs (nom, email, etc.)
            $em->flush();

            $this->addFlash('success', $t->trans('✅ Profil mis à jour avec succès !'));

            // ⚠️ adapte la route si tu n’as pas “app_profil”
            return $this->redirectToRoute('app_profil', status: Response::HTTP_SEE_OTHER);
        }

        // 4) Affichage initial ou erreurs de validation
        return $this->render('profil/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
