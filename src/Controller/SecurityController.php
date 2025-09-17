<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * Page de connexion.
     * - On récupère la dernière erreur d'authentification (s'il y en a une)
     * - On récupère le dernier identifiant saisi (pour préremplir le champ)
     * - On affiche le template Twig "security/login.html.twig"
     */
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Astuce : si l'utilisateur est déjà connecté, on peut le rediriger ailleurs (ex: dashboard).
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('app_dashboard');
        // }

        // Récupère l'erreur de login (mauvais mot de passe, compte inconnu, etc.)
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupère le dernier nom d'utilisateur saisi (email/username) pour le remettre dans le formulaire
        $lastUsername = $authenticationUtils->getLastUsername();

        // On envoie ces infos au template pour affichage
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Déconnexion.
     * Cette méthode est VOLONTAIREMENT vide :
     * le firewall de Symfony intercepte la route /logout et fait la déconnexion à notre place.
     * (Voir security.yaml > firewalls > logout)
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // On ne met rien ici : Symfony gère tout via la config de sécurité.
        throw new \LogicException('Cette méthode est vide : la déconnexion est gérée par le firewall.');
    }
}
