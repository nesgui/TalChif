<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'événement',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire'),
                    new Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Le nom doit faire au moins {{ limit }} caractères',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 6,
                ],
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire'),
                    new Length(
                        min: 10,
                        minMessage: 'La description doit faire au moins {{ limit }} caractères'
                    ),
                ],
            ])
            ->add('dateEvenement', DateType::class, [
                'label' => 'Date de l\'événement',
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new NotBlank(message: 'La date est obligatoire'),
                    new GreaterThan(
                        value: 'today',
                        message: 'La date doit être dans le futur'
                    ),
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'constraints' => [
                    new NotBlank(message: 'Le lieu est obligatoire'),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [
                    new NotBlank(message: 'L\'adresse est obligatoire'),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'placeholder' => 'N\'Djamena, Moundou, Sarh...',
                ],
                'constraints' => [
                    new NotBlank(message: 'La ville est obligatoire'),
                ],
            ])
            ->add('placesDisponibles', IntegerType::class, [
                'label' => 'Places disponibles',
                'attr' => [
                    'min' => 1,
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nombre de places est obligatoire'),
                    new GreaterThan(
                        value: 0,
                        message: 'Le nombre de places doit être supérieur à 0'
                    ),
                ],
            ])
            ->add('prixSimple', MoneyType::class, [
                'label' => 'Prix billet simple (XAF)',
                'currency' => 'XAF',
                'divisor' => 1,
                'constraints' => [
                    new NotBlank(message: 'Le prix simple est obligatoire'),
                    new GreaterThan(
                        value: 0,
                        message: 'Le prix doit être supérieur à 0'
                    ),
                ],
            ])
            ->add('prixVip', MoneyType::class, [
                'label' => 'Prix billet VIP (XAF) - Optionnel',
                'currency' => 'XAF',
                'divisor' => 1,
                'required' => false,
                'help' => 'Laissez vide si l\'événement n\'a pas de billets VIP',
            ])
            ->add('affichePrincipale', TextType::class, [
                'label' => 'URL affiche principale',
                'required' => false,
                'attr' => [
                    'placeholder' => '/images/evenements/nom-evenement.jpg',
                ],
                'help' => 'Chemin vers l\'image principale de l\'événement',
            ])
            ->add('autresAffiches', TextType::class, [
                'label' => 'URL autres affiches',
                'required' => false,
                'attr' => [
                    'placeholder' => '/images/evenements/nom-evenement-2.jpg',
                ],
                'help' => 'Séparez les URLs par des virgules',
            ])
            ->add('imageBillet', TextType::class, [
                'label' => 'URL image billet',
                'required' => false,
                'attr' => [
                    'placeholder' => '/images/billets/nom-evenement-ticket.jpg',
                ],
                'help' => 'Image qui apparaîtra sur le billet',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
