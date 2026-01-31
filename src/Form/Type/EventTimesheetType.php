<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Dto\EventTimesheetDto;
use App\Form\Builder\DateRangeBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventTimesheetType extends AbstractType
{
    public function __construct(
        private readonly DateRangeBuilder $dateRangeBuilder,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->dateRangeBuilder->addDateFields($builder, 'startAt', 'endAt', shortcutOptions: [
            'label' => 'Date',
            'attr' => [
                'class' => 'shorcuts_date',
                'autocomplete' => 'off',
                'data-single-date' => 'true',
            ],
        ]);

        $builder
            ->add('hours', TextType::class, [
                'label' => 'Horaires',
                'required' => false,
                'attr' => [
                    'class' => 'timesheet-hours',
                ],
            ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);
        $this->dateRangeBuilder->finishView($view, $form);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventTimesheetDto::class,
        ]);
    }
}
