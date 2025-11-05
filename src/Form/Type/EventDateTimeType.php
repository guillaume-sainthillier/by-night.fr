<?php

/*
 * This file is part of By Night.
 * (c) 2013-2025 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Dto\EventDateTimeDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventDateTimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDateTime', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'attr' => [
                    'placeholder' => 'Date et heure de début',
                ],
            ])
            ->add('endDateTime', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'attr' => [
                    'placeholder' => 'Date et heure de fin',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventDateTimeDto::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_event_date_time';
    }
}
