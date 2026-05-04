<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CommissionRateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('rate', NumberType::class, [
            'label' => 'Taux de commission',
            'required' => true,
            'html5' => true,
            'scale' => 4,
            'constraints' => [
                new Assert\NotNull(),
                new Assert\GreaterThan(
                    value: 0,
                    message: 'Cette valeur doit être supérieure à {{ compared_value }}.'
                ),
                new Assert\LessThan(
                    value: 1,
                    message: 'Cette valeur doit être inférieure à {{ compared_value }}.'
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
