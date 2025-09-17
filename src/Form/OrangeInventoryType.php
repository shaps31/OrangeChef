<?php

namespace App\Form;

use App\Entity\OrangeInventory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrangeInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('variety', ChoiceType::class, [
                'label' => 'Variété d\'orange',
                'choices' => [
                    '🍊 Navel' => 'navel',
                    '🟠 Valencia' => 'valencia',
                    '🔴 Sanguine' => 'sanguine',
                    '🟡 Mandarine' => 'mandarine',
                    '🟢 Bergamote' => 'bergamote',
                    '🍊 Autre' => 'autre',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => [
                    'placeholder' => '10',
                    'min' => 1,
                    'class' => 'form-control'
                ]
            ])
            ->add('purchaseDate', DateType::class, [
                'label' => 'Date d\'achat',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'data' => new \DateTime()
            ])
            ->add('expirationDate', DateType::class, [
                'label' => 'Date d\'expiration estimée',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'data' => new \DateTime('+2 weeks')
            ])
            ->add('condition', ChoiceType::class, [
                'label' => 'État',
                'choices' => [
                    '🟢 Excellent' => 'excellent',
                    '🟡 Bon' => 'good',
                    '🟠 Correct' => 'fair',
                    '🔴 Mauvais' => 'poor',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Informations supplémentaires sur ce lot d\'oranges...',
                    'rows' => 3,
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrangeInventory::class,
        ]);
    }
}
