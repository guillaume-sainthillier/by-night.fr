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
}
