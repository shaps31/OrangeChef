<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegacyRedirectController extends AbstractController
{
    #[Route('/orange', name: 'legacy_orange', methods: ['GET'])]
    public function orange(): Response
    {
        return $this->redirectToRoute('app_recipe_index', [], 301);
    }
}
