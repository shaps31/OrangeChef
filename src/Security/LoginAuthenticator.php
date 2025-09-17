<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Authentificateur "formulaire de login".
 * - lit l'email + mot de passe postés
 * - vérifie le mot de passe
 * - gère le CSRF et "se souvenir de moi"
 * - redirige ensuite vers la page demandée ou l'accueil
 */
class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    // Petit trait fourni par Symfony : il sait retrouver l'URL
    // que l’utilisateur voulait initialement visiter (avant d’être bloqué par la sécurité).
    use TargetPathTrait;

    // Nom de la route du formulaire de connexion.
    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Construire le "passeport" de connexion à partir de la requête POST.
     * Le Passport = qui se connecte + avec quels "papiers" + quels badges (CSRF, remember me).
     */
    public function authenticate(Request $request): Passport
    {
        // On récupère les champs envoyés par le formulaire.
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');
        $csrf = $request->request->get('_csrf_token');

        // On mémorise le dernier email saisi pour le réafficher en cas d’erreur.
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // On retourne un Passport :
        // - UserBadge : comment charger l’utilisateur (ici par email)
        // - PasswordCredentials : le mot de passe saisi (Symfony fera la vérif)
        // - Badges : protections/infos complémentaires (CSRF, RememberMe)
        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                // Sécurité formulaire : empêche un faux site d’envoyer le POST à ta place.
                new CsrfTokenBadge('authenticate', $csrf),
                // Option "Se souvenir de moi" (cookie long terme, si activée côté firewall).
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * Que faire après une connexion réussie ?
     * On redirige vers la page initialement demandée (si connue), sinon vers l'accueil.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Si l’utilisateur avait tenté d’ouvrir une page protégée, on le renvoie là-bas.
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Sinon, destination par défaut : la page d’accueil.
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    /**
     * Où se trouve la page de login ?
     * Symfony l’utilise quand il a besoin de t’y renvoyer (ex : échec de connexion).
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
