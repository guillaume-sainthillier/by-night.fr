<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Entity\City;
use App\Entity\Country;
use IntlListFormatter;
use MessageFormatter;
use RuntimeException;
use Twig\Attribute\AsTwigFilter;

final class LocationExtension
{
    /**
     * "Haute-Garonne (31)": the department of a city, with its number when it has one; null when it has no department.
     */
    #[AsTwigFilter(name: 'department')]
    public function department(City $city): ?string
    {
        $department = $city->getParent()?->getName();
        if (null === $department) {
            return null;
        }

        $code = (string) $city->getAdmin2Code();

        // French department numbers ("31", "2A", "974"); elsewhere the codes mean nothing to visitors
        return 1 === preg_match('/^\d/', $code) ? \sprintf('%s (%s)', $department, $code) : $department;
    }

    /**
     * "France, Suisse, Monaco, Belgique et 5 territoires d'Outre-Mer": countries listed in a sentence. The overseas
     * territories become one item that still adds up with their count; France leads, the others keep their order.
     *
     * @param array<string, string> $countries display names keyed by ISO code (FR, CH, RE…), the busiest first
     */
    #[AsTwigFilter(name: 'countries_summary')]
    public function countriesSummary(array $countries): string
    {
        // An empty list formats to "", which the failure check below would take for an error
        if ([] === $countries) {
            return '';
        }

        $overseas = array_intersect_key($countries, array_flip(Country::FRENCH_OVERSEAS));
        $others = array_diff_key($countries, $overseas);
        if (isset($others['FR'])) {
            $others = ['FR' => $others['FR']] + $others;
        }

        $items = array_values($others);
        if ([] !== $overseas) {
            $items[] = MessageFormatter::formatMessage(
                'fr',
                "{count, plural, =1 {{name}} other {# territoires d''Outre-Mer}}",
                ['count' => \count($overseas), 'name' => array_first($overseas)],
            ) ?: throw new RuntimeException(intl_get_error_message());
        }

        return new IntlListFormatter('fr')->format($items) ?: throw new RuntimeException(intl_get_error_message());
    }
}
