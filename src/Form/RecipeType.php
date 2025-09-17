<?php

namespace App\Form;

use App\Entity\Recipe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la recette',
                'attr' => [
                    'placeholder' => 'Ex: Tarte à l\'orange maison',
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Décrivez votre délicieuse recette à l\'orange...',
                    'rows' => 3,
                    'class' => 'form-control'
                ]
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    '🍰 Dessert' => 'dessert',
                    '🥤 Boisson' => 'boisson',
                    '🍽️ Plat principal' => 'plat',
                    '🥄 Sauce' => 'sauce',
                    '🍯 Confiture' => 'confiture',
                    '🥗 Salade' => 'salade',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'Difficulté',
                'choices' => [
                    '⭐ Facile' => 'facile',
                    '⭐⭐ Moyen' => 'moyen',
                    '⭐⭐⭐ Difficile' => 'difficile',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('preparationTime', IntegerType::class, [
                'label' => 'Temps de préparation (minutes)',
                'attr' => [
                    'placeholder' => '30',
                    'min' => 1,
                    'class' => 'form-control'
                ]
            ])
            ->add('cookingTime', IntegerType::class, [
                'label' => 'Temps de cuisson (minutes)',
                'attr' => [
                    'placeholder' => '45',
                    'min' => 0,
                    'class' => 'form-control'
                ]
            ])
            ->add('servings', IntegerType::class, [
                'label' => 'Nombre de portions',
                'attr' => [
                    'placeholder' => '4',
                    'min' => 1,
                    'class' => 'form-control'
                ]
            ])
            ->add('ingredients', TextareaType::class, [
                'label' => 'Ingrédients',
                'attr' => [
                    'placeholder' => "- 3 oranges bio\n- 200g de farine\n- 100g de sucre\n- 2 œufs\n- ...",
                    'rows' => 8,
                    'class' => 'form-control'
                ]
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Instructions',
                'attr' => [
                    'placeholder' => "1. Préchauffez le four à 180°C\n2. Pressez les oranges...\n3. Mélangez les ingrédients secs...",
                    'rows' => 10,
                    'class' => 'form-control'
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Photo de la recette',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WebP)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Rendre cette recette publique',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
