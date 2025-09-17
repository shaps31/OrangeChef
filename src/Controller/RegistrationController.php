<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Avatar
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
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l\'envoi de l\'avatar.');
                }
            }

            // ✅ Rôles (optionnel) — toujours un tableau
            $roles = $form->get('roles')->getData();
            $user->setRoles($roles ?? []);

            // ✅ Mot de passe
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );

            // ✅ Statut vérifié à false
            $user->setIsVerified(false);

            // ✅ Enregistrement
            $entityManager->persist($user);
            $entityManager->flush();

            // ✅ Envoi email de confirmation (ne bloque pas l'inscription)
            try {
                $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    (new TemplatedEmail())
                        ->from(new Address('no_reply@orangechef.com', 'OrangeChef Bot'))
                        ->to((string) $user->getEmail())
                        ->subject('Confirme ton adresse email')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                        ->context(['user' => $user])
                );
                $this->addFlash('success', '📧 Email de confirmation envoyé. Vérifie ta boîte mail.');
            } catch (\Throwable $e) {
                // Log interne et message utilisateur non bloquant
                $this->addFlash('warning', "⚠️ Compte créé, mais l'email de confirmation n'a pas pu être envoyé pour le moment. Réessaie plus tard.");
            }

            // ✅ Message de succès
            $this->addFlash('success', '🎉 Ton compte a été créé avec succès ! Vérifie ta boîte mail pour confirmer ton adresse.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', '✅ Ton adresse email a bien été confirmée.');
        return $this->redirectToRoute('app_orange_index');
    }
}
