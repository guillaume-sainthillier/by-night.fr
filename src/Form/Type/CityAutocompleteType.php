<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CityAutocompleteType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateRange', DateRangeType::class, [
                'label' => null,
                'ranges' => 'preset',
            ])
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'À',
                'attr' => [
                    'class' => 'city-picker',
                ],
            ])
            ->add('city', HiddenType::class, [
                'label' => false,
                'attr' => [
                    'class' => 'city-value',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une ville pour continuer'),
                ],
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
