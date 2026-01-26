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
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventTimesheetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startAt', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'timesheet-start',
                ],
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'timesheet-end',
                ],
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('hours', TextType::class, [
                'label' => 'Horaires affichés',
                'required' => false,
                'attr' => [
                    'placeholder' => 'De 20h à 23h',
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
