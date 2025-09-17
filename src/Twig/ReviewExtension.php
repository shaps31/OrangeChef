<?php

namespace App\Twig;

use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ReviewExtension extends AbstractExtension
{
    public function __construct(
        private ReviewRepository $reviews,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_reviews', [$this, 'pendingReviews']),
        ];
    }

    public function pendingReviews(): int
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }
        return $this->reviews->countPending(); // méthode ajoutée plus tôt
    }
}
