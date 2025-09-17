<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'form.nom',
                'required' => true,
            ])
            ->add('prenom', TextType::class, [
                'label' => 'form.prenom',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'required' => true,
                'attr' => ['readonly' => true],
            ])
            ->add('avatar', FileType::class, [
                'label' => 'form.avatar',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'form.errors.invalid_image_type',
                        'minWidth' => 80,
                        'minHeight' => 80,
                        'maxWidth' => 2000,
                        'maxHeight' => 2000,
                        'minWidthMessage' => 'form.errors.min_width',
                        'maxWidthMessage' => 'form.errors.max_width',
                        'minHeightMessage' => 'form.errors.min_height',
                        'maxHeightMessage' => 'form.errors.max_height',
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
