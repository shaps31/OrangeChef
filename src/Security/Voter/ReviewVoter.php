<?php

namespace App\Security\Voter;

use App\Entity\Review;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Qui peut faire quoi sur une Review ?
 * - EDIT / DELETE : l'auteur de l'avis ou un admin
 * - APPROVE       : uniquement admin
 */
final class ReviewVoter extends Voter
{
    public const EDIT    = 'REVIEW_EDIT';
    public const DELETE  = 'REVIEW_DELETE';
    public const APPROVE = 'REVIEW_APPROVE';

    /** On ne vote que pour ces attributs ET si le sujet est bien une Review */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Review
            && \in_array($attribute, [self::EDIT, self::DELETE, self::APPROVE], true);
    }

    /** La décision : true = autorisé, false = refusé */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // 1) Pas connecté → rien n'est autorisé
        if (!\is_object($user)) {
            return false;
        }

        // 2) Admin → tout est autorisé
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // 3) Règles “non-admin”
        /** @var Review $review */
        $review = $subject;
        $isAuthor = $this->isSameUser($review->getAuthor(), $user);

        return match ($attribute) {
            self::EDIT, self::DELETE => $isAuthor, // seul l'auteur peut modifier/supprimer
            self::APPROVE            => false,     // réservé à l'admin (déjà géré plus haut)
            default                  => false,
        };
    }

    /**
     * Compare deux utilisateurs de façon robuste (ID → gère les proxys Doctrine).
     */
    private function isSameUser(?object $a, ?object $b): bool
    {
        if (!$a || !$b) {
            return false;
        }
        // Si les 2 objets ont un getId(), on compare les IDs (plus fiable)
        if (\method_exists($a, 'getId') && \method_exists($b, 'getId')) {
            return $a->getId() === $b->getId();
        }
        // Sinon, on tente la comparaison directe (rarement utilisée)
        return $a === $b;
    }
}
