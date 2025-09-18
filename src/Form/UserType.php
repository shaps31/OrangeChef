<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;


class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordRequired = (bool) $options['password_required'];

        $builder
            ->add('displayName', null, [
                'required' => false,
                'label' => 'Nom affiché',
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'E-mail',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir un e-mail.']),
                    new Assert\Email(['message' => 'E-mail invalide.']),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'required' => $options['password_required'], // true en création, false en édition
                'mapped'   => false,
                'label'    => 'Mot de passe',
                'attr'     => ['autocomplete' => 'new-password'],
                'constraints' => $options['password_required']
                    ? [
                        new Assert\NotBlank(['message' => 'Veuillez saisir un mot de passe.']),
                        new Assert\Length(['min' => 8, 'minMessage' => '8 caractères minimum.']),
                    ]
                    : [
                        // autorise vide OU longueur >= 8
                        new Assert\Regex([
                            'pattern' => '/^$|.{8,}/u',
                            'message' => '8 caractères minimum.',
                        ]),
                    ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Membre' => 'ROLE_USER',
                    'Admin'  => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('isVerified', CheckboxType::class, [
                'required' => false,
                'label' => 'Compte vérifié',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => User::class,
            'password_required' => false, // ← false par défaut (édition)
        ]);
    }
}
