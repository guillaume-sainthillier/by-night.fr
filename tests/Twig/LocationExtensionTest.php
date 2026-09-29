<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Entity\AdminZone1;
use App\Entity\City;
use App\Twig\LocationExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocationExtensionTest extends TestCase
{
    #[DataProvider('provideDepartments')]
    public function testDepartment(?string $department, ?string $code, ?string $expected): void
    {
        $city = new City();
        $city->setAdmin2Code($code);
        if (null !== $department) {
            $city->setParent(new AdminZone1()->setName($department));
        }

        self::assertSame($expected, new LocationExtension()->department($city));
    }

    /**
     * @return iterable<string, array{string|null, string|null, string|null}>
     */
    public static function provideDepartments(): iterable
    {
        yield 'French department' => ['Haute-Garonne', '31', 'Haute-Garonne (31)'];
        yield 'Corsica' => ['Corse-du-Sud', '2A', 'Corse-du-Sud (2A)'];
        yield 'overseas' => ['Martinique', '972', 'Martinique (972)'];
        yield 'code meaningless abroad' => ['Genève', 'GE', 'Genève'];
        yield 'no code' => ['Bruxelles-Capitale', null, 'Bruxelles-Capitale'];
        yield 'no department' => [null, '31', null];
    }
}
