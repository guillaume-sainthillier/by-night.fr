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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventTimesheetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateRange', DateRangeType::class, [
                'from_field' => 'startAt',
                'to_field' => 'endAt',
                'label' => 'Date',
                'single_date_picker' => true,
            ])
            ->add('hours', TextType::class, [
                'label' => 'Horaires',
                'required' => false,
                'attr' => [
                    'class' => 'timesheet-hours',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventTimesheetDto::class,
        ]);
    }
}
