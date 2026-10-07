<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Creation et renommage d'une organisation.
 *
 * Le slug et le taux de commission ne sont pas exposes : le premier est derive
 * du nom, le second releve de la plateforme.
 */
final class OrganisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nom', TextType::class, [
            'label' => 'Nom de l\'organisation',
            'attr' => [
                'placeholder' => 'Ex. Productions Sahel',
                'maxlength' => 180,
                'autocomplete' => 'organization',
            ],
            'help' => 'Ce nom apparait sur vos billets et vos reglements.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organisation::class,
        ]);
    }
}
