<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Form\Builder\DateRangeBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CityAutocompleteType extends AbstractType
{
    private DateRangeBuilder $dateRangeBuilder;

    public function __construct(DateRangeBuilder $dateRangeBuilder)
    {
        $this->dateRangeBuilder = $dateRangeBuilder;
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $this->dateRangeBuilder->finishView($view, $form);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->dateRangeBuilder->addShortcutDateFields($builder, 'from', 'to');
        $builder
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
                    new NotBlank([
                        'message' => 'Veuillez choisir une ville pour continuer',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
