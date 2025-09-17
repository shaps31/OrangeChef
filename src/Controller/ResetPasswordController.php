<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Tout ce qui concerne "Mot de passe oublié"
 * L’URL de base est /reset-password (définie sur la classe)
 */
#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    // Le trait fournit des petites aides (stockage du token en session, etc.)
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper, // service du bundle ResetPassword
        private EntityManagerInterface $entityManager              // accès BDD (User)
    ) {}

    /**
     * Étape 1 — Affiche et traite le formulaire "J'ai oublié mon mot de passe".
     * On ne dit jamais si l’email existe ou non → sécurité/anti-énumération.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        // Formulaire avec juste un champ "email"
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        // Si soumis et valide → on tente d'envoyer un e-mail de réinitialisation
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail(
                $email,
                $mailer,
                $translator
            );
        }

        // Affichage initial (ou en cas d’erreurs de validation)
        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Étape 1 bis — Page "check email".
     * On y arrive toujours après l’étape 1, que l’email existe ou pas.
     * Si on n’a pas de token en session (accès direct), on en crée un "faux"
     * pour ne rien divulguer.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Récupère le token stocké en session par processSendingPasswordResetEmail()
        // ou fabrique un token bidon (pour éviter de révéler des infos)
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Étape 2 — Lien reçu par e-mail : l’URL contient un {token}.
     * On valide le token, on récupère l’utilisateur, puis on affiche
     * le formulaire "Nouveau mot de passe".
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator,
        ?string $token = null
    ): Response {
        // 1) Si le token est présent dans l’URL, on le déplace en session
        // puis on redirige vers la même route sans token dans l’URL
        // (évite fuites dans l’historique, extensions, etc.)
        if ($token) {
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        // 2) Sinon, on le lit depuis la session
        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        // 3) On valide le token et on récupère l’utilisateur concerné
        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            // Token invalide/expiré → message générique + retour au formulaire "oubli"
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // 4) Token OK → on affiche le formulaire avec "plainPassword"
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        // 5) Soumission du nouveau mot de passe
        if ($form->isSubmitted() && $form->isValid()) {
            // Le token ne doit servir qu’une fois
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hash + enregistrement
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // Nettoyage des infos en session puis direction la page de login
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('app_login');
        }

        // Affichage du formulaire (GET ou erreurs)
        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    /**
     * Méthode interne — génère un token et envoie l’e-mail de réinitialisation.
     * Toujours silencieuse : on redirige vers "check email" même si l’email n’existe pas.
     */
    private function processSendingPasswordResetEmail(
        string $emailFormData,
        MailerInterface $mailer,
        TranslatorInterface $translator
    ): RedirectResponse {
        // Recherche l’utilisateur (silence si absent)
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Si pas d’utilisateur → on redirige quand même vers "check email"
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        // Génère un jeton de réinitialisation (peut lever une exception, gérée ci-dessous)
        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // Même logique silencieuse : on ne révèle rien, on redirige
            return $this->redirectToRoute('app_check_email');
        }

        // Prépare l’e-mail basé sur un template Twig
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@orangechef.com', 'Orange Chef Bot'))
            ->to((string) $user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig') // le template affiche le lien avec le token
            ->context([
                'resetToken' => $resetToken, // injecté dans le template
            ]);

        // Envoi
        $mailer->send($email);

        // On garde l’objet token en session pour l’écran "check email"
        $this->setTokenObjectInSession($resetToken);

        // Redirection systématique (même UX pour tous)
        return $this->redirectToRoute('app_check_email');
    }
}
