<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire'),
                    new Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères'),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'constraints' => [
                    new NotBlank(message: 'Le nom complet est obligatoire'),
                    new Length(
                        min: 2,
                        max: 255,
                        minMessage: 'Le nom complet doit faire au moins {{ limit }} caractères',
                        maxMessage: 'Le nom complet ne peut pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: '/^[\p{L}\p{M}\s\-\']+$/u',
                        message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes'
                    ),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+235 XXXXXXXX',
                ],
                'constraints' => [
                    new Length(max: 20, maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^[\d\s+\-()]*$/',
                        message: 'Le téléphone ne peut contenir que des chiffres, espaces, +, - et parenthèses'
                    ),
                ],
            ]);

        // Ajouter le champ mot de passe seulement pour la création
        if ($options['include_password']) {
            $builder
                ->add('password', PasswordType::class, [
                    'label' => 'Mot de passe',
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Le mot de passe est obligatoire'),
                        new Length(
                            min: 6,
                            max: 4096,
                            minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères',
                            maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères'
                        ),
                        new Regex(
                            pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/',
                            message: 'Le mot de passe ne doit pas contenir de caractères de contrôle'
                        ),
                    ],
                ])
                ->add('password_confirm', PasswordType::class, [
                    'label' => 'Confirmer le mot de passe',
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'La confirmation du mot de passe est obligatoire'),
                    ],
                ]);
        }

        // Ajouter le champ rôle seulement pour l'admin
        if ($options['include_role']) {
            $builder->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Client' => 'CLIENT',
                    'Organisateur' => 'ORGANISATEUR',
                    'Administrateur' => 'ADMIN',
                ],
                'placeholder' => 'Choisir un rôle',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'include_password' => false,
            'include_role' => false,
        ]);
    }
}
