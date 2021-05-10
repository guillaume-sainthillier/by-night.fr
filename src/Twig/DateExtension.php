<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use DateTime;
use DateTimeInterface;
use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFilter;

class DateExtension extends Extension
{
    public function getFilters()
    {
        return [
            new TwigFilter('diff_date', [$this, 'diffDate']),
            new TwigFilter('stats_diff_date', [$this, 'statsDiffDate']),
            new TwigFilter('datetime', [$this, 'getDateTime']),
        ];
    }

    public function getDateTime($string)
    {
        return new DateTime($string);
    }

    public function diffDate(DateTimeInterface $date)
    {
        return $this->statsDiffDate($date)['full'];
    }

    public function statsDiffDate(DateTimeInterface $date)
    {
        $diff = $date->diff(new DateTime());

        if ($diff->y > 0) { //Années
            return [
                'short' => sprintf('%d an%s', $diff->y, $diff->y > 1 ? 's' : ''),
                'long' => sprintf('%d an%s', $diff->y, $diff->y > 1 ? 's' : ''),
                'full' => sprintf('Il y a %d an%s', $diff->y, $diff->y > 1 ? 's' : ''),
            ];
        } elseif ($diff->m > 0) { //Mois
            return [
                'short' => sprintf('%d mois', $diff->m),
                'long' => sprintf('%d mois', $diff->m),
                'full' => sprintf('Il y a %d mois', $diff->m),
            ];
        } elseif ($diff->d > 0) { //Jours
            return [
                'short' => sprintf('%d j', $diff->d),
                'long' => sprintf('%d jours', $diff->d),
                'full' => sprintf('Il y a %d jours', $diff->d),
            ];
        } elseif ($diff->h > 0) { //Heures
            return [
                'short' => sprintf('%d h', $diff->h),
                'long' => sprintf('%d heure%s', $diff->h, $diff->h > 1 ? 's' : ''),
                'full' => sprintf('Il y a %d heure%s', $diff->h, $diff->h > 1 ? 's' : ''),
            ];
        } elseif ($diff->i > 0) { //Minutes
            return [
                'short' => sprintf('%d min', $diff->i),
                'long' => sprintf('%d minute%s', $diff->i, $diff->i > 1 ? 's' : ''),
                'full' => sprintf('Il y a %d minute%s', $diff->i, $diff->i > 1 ? 's' : ''),
            ];
        } elseif ($diff->s > 30) { //Secondes
            return [
                'short' => sprintf('%d s', $diff->s),
                'long' => sprintf('%d seconde%s', $diff->s, $diff->s > 1 ? 's' : ''),
                'full' => sprintf('Il y a %d seconde%s', $diff->s, $diff->s > 1 ? 's' : ''),
            ];
        }

        return [
            'short' => '0 s',
            'long' => "à l'instant",
            'full' => "A l'instant",
        ];
    }
}
