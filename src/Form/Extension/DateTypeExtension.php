<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use Generator;
use IntlDateFormatter;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DateTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritDoc}
     *
     * @psalm-return Generator<int, DateType::class, mixed, void>
     */
    public static function getExtendedTypes(): iterable
    {
        yield DateType::class;
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $format = static fn (Options $options): string|int => 'single_text' === $options['widget'] ? DateType::HTML5_FORMAT : IntlDateFormatter::SHORT;

        $resolver->setDefaults([
            'attr' => ['class' => 'widget_datepicker', 'autocomplete' => 'off'],
            'widget' => 'single_text',
            'html5' => false,
            'format' => $format,
        ]);
    }
}
