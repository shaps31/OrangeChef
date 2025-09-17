<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b
            ->add('rating', ChoiceType::class, [
                'label'        => 'Votre note',
                'choices'      => [5 => 5, 4 => 4, 3 => 3, 2 => 2, 1 => 1],
                'expanded'     => false,
                'multiple'     => false,
                'choice_label' => static fn($v, $k, $i) => str_repeat('★', (int)$i),
            ])

            ->add('comment', TextareaType::class, [
                'label'    => 'Votre avis (optionnel)',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Quelques mots sur la recette…',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
