<?php

namespace App\Security\Voter;

use App\Entity\Review;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ReviewVoter extends Voter
{
    public const EDIT     = 'REVIEW_EDIT';
    public const DELETE   = 'REVIEW_DELETE';
    public const APPROVE  = 'REVIEW_APPROVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Review
            && in_array($attribute, [self::EDIT, self::DELETE, self::APPROVE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!is_object($user)) return false;

        // L’admin peut tout faire
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Review $review */
        $review = $subject;

        return match ($attribute) {
            self::EDIT, self::DELETE   => $review->getAuthor() === $user, // auteur de l’avis
            self::APPROVE              => false,                           // réservé à l’admin ci-dessus
        };
    }
}
