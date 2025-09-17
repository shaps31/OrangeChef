<?php

namespace App\Controller;

use App\Entity\OrangeInventory;
use App\Entity\Recipe;
use App\Form\OrangeInventoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur de gestion de l'inventaire d'oranges de l'utilisateur connecté.
 *
 * - Le préfixe de route "/inventaire" s'applique à toutes les actions.
 * - L'attribut IsGranted('ROLE_USER') au niveau classe protège toutes les actions :
 *   l'utilisateur doit être authentifié pour y accéder.
 */
#[Route('/inventaire')]
#[IsGranted('ROLE_USER')]
class InventoryController extends AbstractController
{
    /**
     * Page d'accueil de l'inventaire.
     * Liste l'inventaire de l'utilisateur (trié par date d'expiration),
     * calcule quelques stats et propose des suggestions de recettes.
     */
    #[Route('/', name: 'app_inventory_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Utilisateur courant (fourni par le système de sécurité)
        $user = $this->getUser();

        // Récupération des items d'inventaire de l'utilisateur, triés par date d'expiration croissante
        $inventory = $entityManager->getRepository(OrangeInventory::class)
            ->findBy(['user' => $user], ['expirationDate' => 'ASC']);

        // --- Statistiques simples sur le stock ---
        // Somme des quantités
        $totalOranges = array_sum(array_map(fn($item) => $item->getQuantity(), $inventory));

        // Items qui expirent bientôt (implémentation déléguée aux méthodes du modèle)
        $expiringSoon = array_filter($inventory, fn($item) => $item->isExpiringSoon() && !$item->isExpired());

        // Items déjà expirés
        $expired = array_filter($inventory, fn($item) => $item->isExpired());

        // Suggestions de recettes (ex : les plus vues) — logique encapsulée plus bas
        $suggestions = $this->getRecipeSuggestions($entityManager, $inventory);

        // Rendu du template avec les données utiles à l'affichage
        return $this->render('inventory/index.html.twig', [
            'inventory' => $inventory,
            'stats' => [
                'total' => $totalOranges,
                'expiringSoon' => count($expiringSoon),
                'expired' => count($expired),
                // Nombre de variétés distinctes présentes dans le stock
                'varieties' => count(array_unique(array_map(fn($item) => $item->getVariety(), $inventory)))
            ],
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Création d'un nouvel item d'inventaire.
     * - Affiche le formulaire à l'arrivée (GET).
     * - Persiste l'item au submit valide (POST), affecté à l'utilisateur courant.
     */
    #[Route('/nouveau', name: 'app_inventory_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $inventory = new OrangeInventory();

        // Génération et gestion du formulaire (hydrate $inventory)
        $form = $this->createForm(OrangeInventoryType::class, $inventory);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide, on sauvegarde l'item
        if ($form->isSubmitted() && $form->isValid()) {
            // Associer l'item à l'utilisateur connecté (propriété de l'item)
            $inventory->setUser($this->getUser());

            $entityManager->persist($inventory);
            $entityManager->flush();

            $this->addFlash('success', '🍊 Stock ajouté avec succès !');

            // Retour à la liste
            return $this->redirectToRoute('app_inventory_index');
        }

        // Affichage du formulaire (page GET initiale ou POST non valide)
        return $this->render('inventory/new.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    /**
     * Consultation d'un item d'inventaire spécifique.
     * - Vérifie que l'utilisateur connecté est bien propriétaire de l'item.
     */
    #[Route('/{id}', name: 'app_inventory_show', methods: ['GET'])]
    public function show(OrangeInventory $inventory): Response
    {
        // Sécurité : seul le propriétaire peut voir l'item
        if ($inventory->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('inventory/show.html.twig', [
            'inventory' => $inventory,
        ]);
    }

    /**
     * Modification d'un item d'inventaire.
     * - Vérifie la propriété.
     * - Sauvegarde si le formulaire est valide.
     */
    #[Route('/{id}/modifier', name: 'app_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OrangeInventory $inventory, EntityManagerInterface $entityManager): Response
    {
        // Sécurité : seul le propriétaire peut éditer
        if ($inventory->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Formulaire pré-rempli avec l'entité existante
        $form = $this->createForm(OrangeInventoryType::class, $inventory);
        $form->handleRequest($request);

        // Sauvegarde si valide
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Stock modifié avec succès !');
            return $this->redirectToRoute('app_inventory_index');
        }

        // Affiche le formulaire sinon
        return $this->render('inventory/edit.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'un item d'inventaire (POST uniquement).
     * - Vérifie la propriété.
     * - Protégé par un token CSRF.
     */
    #[Route('/{id}/supprimer', name: 'app_inventory_delete', methods: ['POST'])]
    public function delete(Request $request, OrangeInventory $inventory, EntityManagerInterface $entityManager): Response
    {
        // Sécurité : seul le propriétaire peut supprimer
        if ($inventory->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérification du token CSRF (généré côté template)
        if ($this->isCsrfTokenValid('delete'.$inventory->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($inventory);
            $entityManager->flush();
            $this->addFlash('success', 'Stock supprimé');
        }

        return $this->redirectToRoute('app_inventory_index');
    }

    /**
     * Page d'alertes d'expiration.
     * - "expiringSoon" : items qui expirent dans les 7 jours.
     * - "expired"      : items déjà expirés.
     * Tout est filtré par utilisateur courant.
     */
    #[Route('/alertes/expiration', name: 'app_inventory_alerts', methods: ['GET'])]
    public function alerts(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Items qui expirent entre maintenant et maintenant + 7 jours (triés croissant)
        $expiringSoon = $entityManager->getRepository(OrangeInventory::class)
            ->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.expirationDate BETWEEN :now AND :soon')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('soon', new \DateTime('+7 days'))
            ->orderBy('i.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Items déjà expirés (triés du plus récemment expiré au plus ancien)
        $expired = $entityManager->getRepository(OrangeInventory::class)
            ->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.expirationDate < :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('i.expirationDate', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('inventory/alerts.html.twig', [
            'expiringSoon' => $expiringSoon,
            'expired' => $expired,
        ]);
    }

    /**
     * Logique interne de suggestion de recettes.
     * Pour rester simple, on propose des recettes publiques les plus vues.
     * (Tu peux plus tard faire matcher les ingrédients/variétés exactes avec l'inventaire).
     *
     * @param EntityManagerInterface $entityManager
     * @param OrangeInventory[]      $inventory  Liste d'objets d'inventaire de l'utilisateur
     * @return Recipe[]              Jusqu'à 4 recettes suggérées
     */
    private function getRecipeSuggestions(EntityManagerInterface $entityManager, array $inventory): array
    {
        // Si pas de stock, pas de suggestion
        if (empty($inventory)) {
            return [];
        }

        // Récupère des recettes publiques, triées par popularité (nombre de vues)
        $recipes = $entityManager->getRepository(Recipe::class)
            ->createQueryBuilder('r')
            ->where('r.isPublic = :public')
            ->setParameter('public', true)
            ->orderBy('r.views', 'DESC')
            ->setMaxResults(6) // on en sélectionne un peu plus pour ensuite réduire proprement
            ->getQuery()
            ->getResult();

        // On limite à 4 pour l'affichage
        return array_slice($recipes, 0, 4);
    }
}
