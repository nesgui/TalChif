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

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire'),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire'),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire'),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+235 XXXXXXXX',
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
                            minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères'
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
