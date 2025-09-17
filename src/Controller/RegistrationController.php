<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class RegistrationController extends AbstractController
{
    // ➜ petites constantes pour éviter les “valeurs magiques”
    private const AVATAR_MAX_BYTES   = 2 * 1024 * 1024; // 2 Mo
    private const AVATAR_MIME_TYPES  = ['image/jpeg','image/png','image/webp'];

    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route('/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        $user = new User();

        // 1) Formulaire d’inscription (classique)
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // 2) Si valide → on crée le compte
        if ($form->isSubmitted() && $form->isValid()) {
            // a) Avatar (optionnel) + garde-fous simples
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile) {
                // type MIME
                if (!\in_array($avatarFile->getMimeType(), self::AVATAR_MIME_TYPES, true)) {
                    $this->addFlash('danger', "Format d'image non pris en charge (JPEG, PNG, WEBP).");
                    return $this->redirectToRoute('app_register', status: Response::HTTP_SEE_OTHER);
                }
                // taille max
                if ($avatarFile->getSize() > self::AVATAR_MAX_BYTES) {
                    $this->addFlash('danger', 'Image trop lourde (max 2 Mo).');
                    return $this->redirectToRoute('app_register', status: Response::HTTP_SEE_OTHER);
                }

                // nom de fichier sûr + unique
                $original = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safe     = $slugger->slug($original)->lower();
                $ext      = $avatarFile->guessExtension() ?: 'bin';
                $uniq     = substr(bin2hex(random_bytes(6)), 0, 12);
                $newName  = sprintf('%s-%s.%s', $safe, $uniq, $ext);

                try {
                    $avatarFile->move($this->getParameter('avatars_directory'), $newName);
                    $user->setAvatar($newName);
                } catch (FileException) {
                    $this->addFlash('danger', "Erreur lors de l'envoi de l'avatar.");
                    return $this->redirectToRoute('app_register', status: Response::HTTP_SEE_OTHER);
                }
            }

            // b) RÔLES
            // ⚠️ Très important : on ne récupère PAS des rôles depuis un form public
            // (sinon élévation de privilèges). On force ROLE_USER.
            $user->setRoles(['ROLE_USER']);

            // c) Mot de passe (hash)
            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));

            // d) Compte non vérifié tant que l'email n'est pas confirmé
            $user->setIsVerified(false);

            // e) Persistance
            $em->persist($user);
            $em->flush();

            // f) Email de confirmation (ne bloque pas l’inscription)
            try {
                $fromAddress = (string) ($this->getParameter('app.mail.from_address') ?? 'no_reply@example.test');
                $fromName    = (string) ($this->getParameter('app.mail.from_name') ?? 'OrangeChef Bot');

                $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    (new TemplatedEmail())
                        ->from(new Address($fromAddress, $fromName))
                        ->to((string) $user->getEmail())
                        ->subject('Confirme ton adresse email')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                        ->context(['user' => $user])
                );
                $this->addFlash('success', '📧 Email de confirmation envoyé. Vérifie ta boîte mail.');
            } catch (\Throwable $e) {
                // on ne bloque pas l’inscription si l’email part mal
                $this->addFlash('warning', "⚠️ Compte créé, mais l'email de confirmation n'a pas pu être envoyé pour le moment.");
            }

            $this->addFlash('success', '🎉 Ton compte a été créé avec succès !');
            return $this->redirectToRoute('app_home');
        }

        // GET initial ou erreurs → on (re)affiche le form
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }


    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $em
    ): Response {
        // 1) On récupère l'ID du user dans l'URL signée (?id=...)
        $id = $request->query->get('id');
        if ($id<=0) {
            // Pas d'id → lien invalide
            $this->addFlash('verify_email_error', 'Lien de vérification invalide.');
            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        /** @var User|null $user */
        // 2) On charge le user ciblé par la vérif
        $user = $em->getRepository(User::class)->find($id);
        if ($user->isVerified()) {
            // id inconnu → lien invalide
            $this->addFlash('info', 'Ton adresse est déjà vérifiée.');
            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        try {
            // 3) On délègue au helper du bundle la vérif de la signature,
            //    de l'expiration, et la mise à jour "isVerified".
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $e) {
            // 4) Si la signature est invalide/expirée, on affiche le message traduit
            $this->addFlash('verify_email_error', $translator->trans($e->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        // 5) Succès
        $this->addFlash('success', '✅ Ton adresse email a bien été confirmée.');
        return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
    }

}
