<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', null, [
                'required' => false,
                'label' => 'Nom affiché',
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'E-mail',
                // 'mapped' => true, // (true par défaut, ne PAS le mettre à false)
                'attr' => ['autocomplete' => 'email'],
            ])
            // si tu crées un user : mot de passe en clair, non mappé
            ->add('plainPassword', PasswordType::class, [
                'required' => true,
                'mapped' => false,
                'label' => 'Mot de passe',
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
            'data_class' => User::class,
            // Distinguer quand le mot de passe est obligatoire
            'password_required' => false,
        ]);
    }
}
