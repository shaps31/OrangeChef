<?php

namespace App\DataFixtures;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RecipeFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer un utilisateur de démonstration s'il n'existe pas
        $demoUser = $manager->getRepository(User::class)->findOneBy(['email' => 'demo@orangechef.fr']);

        if (!$demoUser) {
            $demoUser = new User();
            $demoUser->setEmail('demo@orangechef.fr');
            $demoUser->setNom('Chef');
            $demoUser->setPrenom('Orange');
            $demoUser->setRoles(['ROLE_USER']);
            $demoUser->setIsVerified(true);
            $demoUser->setPassword($this->passwordHasher->hashPassword($demoUser, 'demo123'));
            $manager->persist($demoUser);
        }

        // Recettes :
        $recipesData = [
            [
                'title' => 'Tarte à l\'Orange Maison',
                'description' => 'Une délicieuse tarte à l\'orange avec une pâte sablée croustillante et une crème d\'orange onctueuse.',
                'category' => 'dessert',
                'difficulty' => 'moyen',
                'preparationTime' => 45,
                'cookingTime' => 35,
                'servings' => 8,
                'ingredients' => "- 3 oranges bio\n- 200g de farine\n- 100g de beurre\n- 80g de sucre\n- 2 œufs\n- 200ml de crème fraîche\n- 1 cuillère à soupe de miel",
                'instructions' => "1. Préchauffez le four à 180°C\n2. Préparez la pâte sablée avec la farine, le beurre et 30g de sucre\n3. Étalez la pâte dans un moule à tarte\n4. Pressez les oranges et mélangez le jus avec les œufs, la crème et le reste du sucre\n5. Versez la préparation sur la pâte\n6. Enfournez 35 minutes jusqu'à ce que la surface soit dorée"
            ],
            [
                'title' => 'Jus d\'Orange Vitaminé',
                'description' => 'Un jus d\'orange frais pressé avec une pointe de gingembre pour booster votre énergie.',
                'category' => 'boisson',
                'difficulty' => 'facile',
                'preparationTime' => 10,
                'cookingTime' => 0,
                'servings' => 2,
                'ingredients' => "- 4 oranges juteuses\n- 1 morceau de gingembre frais (2cm)\n- 1 cuillère à café de miel\n- Quelques glaçons",
                'instructions' => "1. Lavez et pressez les oranges\n2. Râpez finement le gingembre\n3. Mélangez le jus d'orange avec le gingembre râpé\n4. Ajoutez le miel et mélangez bien\n5. Servez avec des glaçons\n6. Dégustez immédiatement pour profiter de toutes les vitamines"
            ],
            [
                'title' => 'Salade d\'Oranges aux Épices',
                'description' => 'Une salade rafraîchissante d\'oranges avec des épices orientales, parfaite en dessert léger.',
                'category' => 'salade',
                'difficulty' => 'facile',
                'preparationTime' => 20,
                'cookingTime' => 0,
                'servings' => 4,
                'ingredients' => "- 5 oranges\n- 1 cuillère à café de cannelle\n- 1/2 cuillère à café de gingembre en poudre\n- 2 cuillères à soupe de miel\n- Quelques feuilles de menthe\n- 30g d'amandes effilées",
                'instructions' => "1. Pelez les oranges à vif en retirant toute la peau blanche\n2. Découpez les oranges en rondelles\n3. Disposez les rondelles dans un plat\n4. Mélangez le miel avec les épices\n5. Arrosez les oranges avec ce mélange\n6. Décorez avec la menthe et les amandes\n7. Laissez reposer 30 minutes au frais avant de servir"
            ],
            [
                'title' => 'Confiture d\'Orange Amère',
                'description' => 'Une confiture traditionnelle d\'orange amère, parfaite pour les petits-déjeuners gourmands.',
                'category' => 'confiture',
                'difficulty' => 'moyen',
                'preparationTime' => 30,
                'cookingTime' => 60,
                'servings' => 10,
                'ingredients' => "- 1kg d'oranges amères\n- 800g de sucre cristallisé\n- Le jus d'1 citron\n- 500ml d'eau",
                'instructions' => "1. Lavez et coupez les oranges en fines lamelles\n2. Faites tremper les oranges dans l'eau froide pendant 24h\n3. Égouttez et faites bouillir dans l'eau pendant 20 minutes\n4. Ajoutez le sucre et le jus de citron\n5. Laissez cuire à feu doux pendant 45 minutes en remuant\n6. Testez la consistance sur une assiette froide\n7. Versez en pots stérilisés et fermez hermétiquement"
            ],
            [
                'title' => 'Poulet à l\'Orange Caramélisé',
                'description' => 'Un plat principal savoureux avec du poulet laqué à l\'orange et ses légumes de saison.',
                'category' => 'plat',
                'difficulty' => 'difficile',
                'preparationTime' => 25,
                'cookingTime' => 45,
                'servings' => 4,
                'ingredients' => "- 4 cuisses de poulet\n- 2 oranges\n- 2 cuillères à soupe de miel\n- 1 cuillère à soupe de sauce soja\n- 2 carottes\n- 1 oignon\n- 2 gousses d'ail\n- Thym frais\n- Huile d'olive",
                'instructions' => "1. Préchauffez le four à 200°C\n2. Faites dorer les cuisses de poulet dans une cocotte\n3. Pressez les oranges et mélangez le jus avec le miel et la sauce soja\n4. Émincez l'oignon, coupez les carottes en rondelles\n5. Ajoutez les légumes autour du poulet\n6. Versez le mélange orange-miel sur le tout\n7. Enfournez 45 minutes en arrosant régulièrement\n8. Servez chaud avec du riz ou des pommes de terre"
            ],
            [
                'title' => 'Sauce à l\'Orange pour Canard',
                'description' => 'Une sauce raffinée à l\'orange, parfaite pour accompagner le canard ou d\'autres viandes.',
                'category' => 'sauce',
                'difficulty' => 'difficile',
                'preparationTime' => 15,
                'cookingTime' => 25,
                'servings' => 6,
                'ingredients' => "- 3 oranges\n- 200ml de bouillon de volaille\n- 2 cuillères à soupe de vinaigre balsamique\n- 1 cuillère à soupe de miel\n- 1 échalote\n- 30g de beurre\n- Sel et poivre",
                'instructions' => "1. Pressez 2 oranges et prélevez les zestes de la 3ème\n2. Émincez finement l'échalote\n3. Faites revenir l'échalote dans une casserole\n4. Ajoutez le jus d'orange et le bouillon\n5. Laissez réduire de moitié à feu vif\n6. Ajoutez le vinaigre, le miel et les zestes\n7. Laissez mijoter 10 minutes\n8. Montez au beurre hors du feu\n9. Filtrez et servez chaud"
            ]
        ];

        foreach ($recipesData as $data) {
            $recipe = new Recipe();
            $recipe->setTitle($data['title']);
            $recipe->setDescription($data['description']);
            $recipe->setCategory($data['category']);
            $recipe->setDifficulty($data['difficulty']);
            $recipe->setPreparationTime($data['preparationTime']);
            $recipe->setCookingTime($data['cookingTime']);
            $recipe->setServings($data['servings']);
            $recipe->setIngredients($data['ingredients']);
            $recipe->setInstructions($data['instructions']);
            $recipe->setAuthor($demoUser);
            $recipe->setPublic(true);
            $recipe->setViews(rand(10, 150)); // Vues aléatoires pour la démo

            $manager->persist($recipe);
        }

        $manager->flush();
    }
}
