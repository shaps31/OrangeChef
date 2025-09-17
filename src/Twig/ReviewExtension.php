<?php

namespace App\Twig;

use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig "ReviewExtension"
 * -> ajoute une fonction Twig: pending_reviews()
 *    qui renvoie le nombre d'avis EN ATTENTE (non approuvés)
 *    mais uniquement pour les admins.
 */
final class ReviewExtension extends AbstractExtension
{
    public function __construct(
        private ReviewRepository $reviews, // pour compter les avis en attente
        private Security $security         // pour savoir si l'utilisateur est admin
    ) {}

    /** Déclare nos fonctions Twig personnalisées */
    public function getFunctions(): array
    {
        return [
            // Utilisable dans Twig: {{ pending_reviews() }}
            new TwigFunction('pending_reviews', [$this, 'pendingReviews']),
        ];
    }

    /** Corps de la fonction Twig */
    public function pendingReviews(): int
    {
        // Si l'utilisateur courant n'est pas admin -> 0 (on ne révèle rien)
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }
        // Sinon, on retourne le nombre d'avis en attente
        return $this->reviews->countPending();
    }
}
