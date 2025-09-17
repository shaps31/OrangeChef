<?php

// src/Controller/RecetteController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecetteController extends AbstractController
{
    #[Route('/recettes-legacy', name: 'recettes_legacy')]
    public function index(): Response
    {
        // Redirection vers la nouvelle page canonique des recettes
        return $this->redirectToRoute('app_recipe_index');
    }
}
