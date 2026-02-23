<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Regex;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Champs de base toujours présents
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
                    new Regex(
                        pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u',
                        message: 'Le nom ne doit pas contenir de caractères de contrôle ou invalides'
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
                        max: 10000,
                        minMessage: 'La description doit faire au moins {{ limit }} caractères',
                        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u',
                        message: 'La description ne doit pas contenir de caractères de contrôle ou invalides'
                    ),
                ],
            ])
            ->add('dateEvenement', DateTimeType::class, [
                'label' => 'Date et heure de l\'événement',
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime())->format('Y-m-d\TH:i'),
                ],
                'constraints' => [
                    new NotBlank(message: 'La date et l\'heure sont obligatoires'),
                    new GreaterThan(
                        value: 'now',
                        message: 'La date et l\'heure doivent être dans le futur'
                    ),
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'constraints' => [
                    new NotBlank(message: 'Le lieu est obligatoire'),
                    new Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u',
                        message: 'Le lieu ne doit pas contenir de caractères de contrôle ou invalides'
                    ),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [
                    new NotBlank(message: 'L\'adresse est obligatoire'),
                    new Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u',
                        message: 'L\'adresse ne doit pas contenir de caractères de contrôle ou invalides'
                    ),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'placeholder' => 'N\'Djamena, Moundou, Sarh...',
                ],
                'constraints' => [
                    new NotBlank(message: 'La ville est obligatoire'),
                    new Length(max: 100, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+$/u',
                        message: 'La ville ne doit pas contenir de caractères de contrôle ou invalides'
                    ),
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
            ]);

        // Ajouter les champs d'upload seulement si allow_file_upload est true
        if ($options['allow_file_upload']) {
            $builder
                ->add('affichePrincipale', FileType::class, [
                    'label' => 'Affiche principale',
                    'required' => false,
                    'mapped' => false,
                    'constraints' => [
                        new File(
                            maxSize: '5M',
                            mimeTypes: [
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                                'image/webp'
                            ],
                            mimeTypesMessage: 'Veuillez uploader une image valide (JPEG, PNG, GIF, WebP)',
                            maxSizeMessage: 'L\'image ne doit pas dépasser 5MB'
                        )
                    ],
                    'help' => 'Formats acceptés: JPEG, PNG, GIF, WebP (max 5MB)',
                ])
                ->add('autresAffiches', FileType::class, [
                    'label' => 'Autres affiches',
                    'required' => false,
                    'mapped' => false,
                    'multiple' => true,
                    'constraints' => [
                        new \Symfony\Component\Validator\Constraints\All([
                            new File(
                                maxSize: '5M',
                                mimeTypes: [
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                    'image/webp'
                                ],
                                mimeTypesMessage: 'Veuillez uploader des images valides (JPEG, PNG, GIF, WebP)',
                                maxSizeMessage: 'Chaque image ne doit pas dépasser 5MB'
                            )
                        ])
                    ],
                    'help' => 'Formats acceptés: JPEG, PNG, GIF, WebP (max 5MB par image) - Plusieurs images possibles',
                ])
                ->add('isActive', CheckboxType::class, [
                    'label' => 'Événement actif (visible par les utilisateurs)',
                    'required' => false,
                    'help' => 'Désactivez cette option pour masquer temporairement l\'événement'
                ])
                ->add('isValide', CheckboxType::class, [
                    'label' => 'Événement validé (approuvé pour publication)',
                    'required' => false,
                    'help' => 'Cochez cette case lorsque l\'événement est prêt à être publié'
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'allow_file_upload' => false,
        ]);
    }
}
