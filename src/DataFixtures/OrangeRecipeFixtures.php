<?php
// src/DataFixtures/OrangeRecipeFixtures.php
namespace App\DataFixtures;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class OrangeRecipeFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['orange'];
    }

    public function load(ObjectManager $em): void
    {
        /** @var User|null $author */
        $author = $em->getRepository(User::class)->findOneBy([]);

        // helper multilignes
        $nl = fn(array $lines) => implode("\n", array_map('trim', $lines));

        $data = [
            [
                'title'       => 'Gâteau à l’orange moelleux',
                'category'    => 'dessert',
                'difficulty'  => 'facile',
                'servings'    => 8,
                'totalTime'   => 55,
                'image'       => null,
                'description' => 'Un gâteau parfumé et fondant, avec zeste et jus d’orange.',
                'ingredients' => $nl([
                    '3 œufs',
                    '150 g de sucre',
                    '100 g de beurre fondu',
                    '150 g de farine',
                    '1 sachet de levure chimique',
                    '2 oranges (zeste + 120 ml de jus)',
                ]),
                'instructions'=> $nl([
                    'Préchauffer le four à 175°C.',
                    'Fouetter œufs et sucre 2–3 min.',
                    'Incorporer beurre fondu, zeste et jus.',
                    'Ajouter farine + levure, mélanger.',
                    'Verser dans un moule beurré, cuire 35–40 min.',
                    'Laisser tiédir avant de démouler.',
                ]),
            ],
            [
                'title'       => 'Salade fenouil, orange & olive',
                'category'    => 'salade',
                'difficulty'  => 'facile',
                'servings'    => 4,
                'totalTime'   => 15,
                'image'       => null,
                'description' => 'Croquante et fraîche avec une vinaigrette à l’orange.',
                'ingredients' => $nl([
                    '2 bulbes de fenouil émincés',
                    '2 oranges en suprêmes',
                    '80 g d’olives noires',
                    '2 c. à s. d’huile d’olive',
                    '1 c. à s. de jus d’orange',
                    'Sel, poivre',
                ]),
                'instructions'=> $nl([
                    'Mélanger fenouil, suprêmes d’orange et olives.',
                    'Fouetter huile + jus, saler, poivrer.',
                    'Assaisonner et servir bien frais.',
                ]),
            ],
            [
                'title'       => 'Poulet à l’orange (rapide)',
                'category'    => 'plat',
                'difficulty'  => 'moyenne',
                'servings'    => 4,
                'totalTime'   => 30,
                'image'       => null,
                'description' => 'Sauté sucré-salé avec sauce orange/soja/gingembre.',
                'ingredients' => $nl([
                    '500 g de blancs de poulet en cubes',
                    '200 ml de jus d’orange',
                    '2 c. à s. de sauce soja',
                    '1 c. à s. de miel',
                    '1 c. à c. de gingembre râpé',
                    '1 c. à s. de maïzena',
                    'Huile, sel, poivre',
                ]),
                'instructions'=> $nl([
                    'Saisir le poulet 5–6 min à feu vif.',
                    'Mélanger jus, soja, miel, gingembre, maïzena.',
                    'Verser la sauce, épaissir en remuant 2–3 min.',
                    'Rectifier l’assaisonnement, servir avec du riz.',
                ]),
            ],
            [
                'title'       => 'Confiture d’orange maison',
                'category'    => 'confiture',
                'difficulty'  => 'moyenne',
                'servings'    => 6,
                'totalTime'   => 90,
                'image'       => null,
                'description' => 'Confiture parfumée avec zeste et jus d’orange.',
                'ingredients' => $nl([
                    '1 kg d’oranges (bio de préférence)',
                    '700 g de sucre',
                    '1 citron (jus)',
                ]),
                'instructions'=> $nl([
                    'Prélever le zeste fin, lever les segments, récupérer le jus.',
                    'Cuire pulpe + zeste + sucre + jus de citron 30–40 min.',
                    'Mettre en pots stérilisés, retourner et laisser refroidir.',
                ]),
            ],
            [
                'title'       => 'Tarte à l’orange sanguine',
                'category'    => 'dessert',
                'difficulty'  => 'difficile',
                'servings'    => 8,
                'totalTime'   => 80,
                'image'       => null,
                'description' => 'Crème à l’orange sanguine sur pâte sablée.',
                'ingredients' => $nl([
                    '1 pâte sablée',
                    '3 œufs',
                    '120 g de sucre',
                    '120 ml de jus d’orange sanguine',
                    '70 g de beurre',
                    'Zeste fin d’une orange',
                ]),
                'instructions'=> $nl([
                    'Cuire la pâte à blanc 15 min à 180°C.',
                    'Cuire à feu doux jus+zeste+œufs+sucre jusqu’à épaississement.',
                    'Hors du feu, incorporer le beurre.',
                    'Verser sur la pâte, refroidir 2 h.',
                ]),
            ],
            [
                'title'       => 'Limonade orange & menthe',
                'category'    => 'boisson',
                'difficulty'  => 'facile',
                'servings'    => 4,
                'totalTime'   => 10,
                'image'       => null,
                'description' => 'Ultra fraîche, sans alcool.',
                'ingredients' => $nl([
                    '500 ml de jus d’orange',
                    '500 ml d’eau pétillante',
                    '1 c. à s. de sirop (ou miel)',
                    'Feuilles de menthe',
                    'Glaçons',
                ]),
                'instructions'=> $nl([
                    'Mélanger jus, eau pétillante et sirop.',
                    'Ajouter menthe et glaçons, servir immédiatement.',
                ]),
            ],
            [
                'title'       => 'Carottes rôties à l’orange',
                'category'    => 'plat',
                'difficulty'  => 'facile',
                'servings'    => 4,
                'totalTime'   => 35,
                'image'       => null,
                'description' => 'Carottes rôties au four, glaçage orange-miel.',
                'ingredients' => $nl([
                    '800 g de carottes',
                    '2 c. à s. d’huile d’olive',
                    '120 ml de jus d’orange',
                    '1 c. à s. de miel',
                    'Sel, poivre',
                ]),
                'instructions'=> $nl([
                    'Préchauffer à 200°C. Mélanger carottes + huile, saler, poivrer.',
                    'Rôtir 20 min, arroser jus + miel, rôtir 10 min de plus.',
                ]),
            ],
            [
                'title'       => 'Vinaigrette à l’orange',
                'category'    => 'sauce',
                'difficulty'  => 'facile',
                'servings'    => 6,
                'totalTime'   => 5,
                'image'       => null,
                'description' => 'Parfaite avec salades de crudités ou poulet.',
                'ingredients' => $nl([
                    '3 c. à s. d’huile d’olive',
                    '2 c. à s. de jus d’orange',
                    '1 c. à c. de moutarde douce',
                    'Sel, poivre',
                ]),
                'instructions'=> $nl([
                    'Fouetter tous les ingrédients jusqu’à émulsion.',
                ]),
            ],
        ];

        $repo = $em->getRepository(Recipe::class);

        foreach ($data as $row) {
            if ($repo->findOneBy(['title' => $row['title']])) {
                continue; // idempotent
            }

            $r = (new Recipe())
                ->setTitle($row['title'])
                ->setDescription($row['description'])
                ->setIngredients($row['ingredients'])
                ->setInstructions($row['instructions'])
                ->setCategory($row['category'])
                ->setDifficulty($row['difficulty'])
                ->setServings($row['servings'])
                ->setCreatedAt(new \DateTimeImmutable('-'.mt_rand(1,30).' days'));

            // >>> temps : remplit preparation_time / cooking_time NOT NULL
            $this->assignTotalMinutes($r, (int) $row['totalTime']);

            if (!empty($row['image']) && method_exists($r, 'setImage')) {
                $r->setImage($row['image']);
            }
            if ($author && method_exists($r, 'setAuthor')) {
                $r->setAuthor($author);
            }

            $em->persist($r);
        }

        $em->flush();
    }

    /**
     * Assigne un temps total en (prépa/cuisson) selon les setters disponibles,
     * en garantissant que la prépa n’est jamais nulle pour les colonnes NOT NULL.
     */
    private function assignTotalMinutes(object $recipe, int $minutes): void
    {
        $minutes = max(1, $minutes);
        $prep = max(1, (int) round($minutes * 0.4));
        $cook = max(0, $minutes - $prep);

        // 1) Préférence : PreparationTime / CookingTime (correspond aux colonnes preparation_time / cooking_time)
        $hasPrep = method_exists($recipe, 'setPreparationTime');
        $hasCook = method_exists($recipe, 'setCookingTime');
        if ($hasPrep || $hasCook) {
            if ($hasPrep) { $recipe->setPreparationTime($prep); }
            if ($hasCook) { $recipe->setCookingTime($cook); }
            return;
        }

        // 2) Variantes de nommage usuelles
        if (method_exists($recipe, 'setPrepTime'))  { $recipe->setPrepTime($prep); }
        if (method_exists($recipe, 'setCookTime'))  { $recipe->setCookTime($cook); }

        // 3) Champs alternatifs uniques
        if (method_exists($recipe, 'setTotalTime')) { $recipe->setTotalTime($minutes); }
        if (method_exists($recipe, 'setDuration'))  { $recipe->setDuration($minutes); }
        if (method_exists($recipe, 'setTime'))      { $recipe->setTime($minutes); }
    }
}
