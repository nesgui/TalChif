<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('current_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe actuel est obligatoire.'),
                ],
            ])
            ->add('new_password', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Le nouveau mot de passe est obligatoire.'),
                    new Length(
                        min: 8,
                        max: 128,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: '/[A-Z]/',
                        message: 'Le mot de passe doit contenir au moins une majuscule.'
                    ),
                    new Regex(
                        pattern: '/[0-9]/',
                        message: 'Le mot de passe doit contenir au moins un chiffre.'
                    ),
                ],
            ])
            ->add('new_password_confirm', PasswordType::class, [
                'label' => 'Confirmer le nouveau mot de passe',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'La confirmation du mot de passe est obligatoire.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

