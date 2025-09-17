<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de contact public.
 * - Contient un "honeypot" (champ caché) pour piéger les bots.
 * - Valide la présence/forme des champs principaux.
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 🐝 Honeypot anti-spam : DOIT rester vide.
            // - mapped=false : n'hydrate pas un objet (on lit la valeur via $form->get('website')->getData()).
            // - hidden : champ non visible pour l'utilisateur. Un bot a tendance à le remplir.
            ->add('website', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
                // Petite contrainte : si rempli → message "Spam détecté."
                // (En pratique, on gère le honeypot dans le contrôleur plutôt que d'afficher cette erreur.)
                'constraints' => [
                    new Length(max: 0, maxMessage: 'Spam détecté.')
                ],
            ])

            // Nom de l’expéditeur
            ->add('name', TextType::class, [
                'label' => 'Votre nom',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre nom.'),
                    new Length(max: 100),
                ],
                'attr' => ['placeholder' => 'Ex: Marie Dupont'],
            ])

            // Email de contact (avec validation de format)
            ->add('email', EmailType::class, [
                'label' => 'Votre email',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre email.'),
                    new Email(message: 'Email invalide.'),
                    new Length(max: 180),
                ],
                'attr' => ['placeholder' => 'vous@example.com'],
            ])

            // Sujet du message
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer un sujet.'),
                    new Length(max: 150),
                ],
                'attr' => ['placeholder' => 'Sujet de votre message'],
            ])

            // Corps du message
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre message.'),
                    new Length(min: 10, minMessage: 'Votre message est trop court (min. 10 caractères).'),
                ],
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Écrivez votre message ici...'
                ],
            ])

            // Bouton d’envoi
            ->add('submit', SubmitType::class, [
                'label' => '📨 Envoyer',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // CSRF activé par défaut → protège le POST contre les faux formulaires
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
