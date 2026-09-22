<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use MessageFormatter;
use RuntimeException;
use Twig\Attribute\AsTwigFilter;

final class DateExtension
{
    /**
     * "Il y a 1 jour", "Il y a 3 heures", "À l'instant": the largest elapsed unit, spelled out and
     * agreed with its count by ICU.
     */
    #[AsTwigFilter(name: 'diff_date')]
    public function diffDate(DateTimeInterface $date): string
    {
        $diff = $date->diff(new DateTimeImmutable());
        [$count, $unit] = match (true) {
            $diff->y > 0 => [$diff->y, 'year'],
            $diff->m > 0 => [$diff->m, 'month'],
            $diff->d > 0 => [$diff->d, 'day'],
            $diff->h > 0 => [$diff->h, 'hour'],
            $diff->i > 0 => [$diff->i, 'minute'],
            $diff->s > 30 => [$diff->s, 'second'],
            default => [0, null],
        };

        if (null === $unit) {
            return "À l'instant";
        }

        return MessageFormatter::formatMessage(
            'fr',
            \sprintf('Il y a {count, number, ::measure-unit/duration-%s unit-width-full-name}', $unit),
            ['count' => $count],
        ) ?: throw new RuntimeException(intl_get_error_message());
    }
}
