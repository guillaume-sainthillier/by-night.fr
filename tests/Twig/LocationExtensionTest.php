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

    /**
     * @param array<string, string> $countries
     */
    #[DataProvider('provideCountries')]
    public function testTheCountriesCaptionLeadsWithFranceAndGroupsTheOverseasTerritories(array $countries, string $expected): void
    {
        self::assertSame($expected, new LocationExtension()->countriesSummary($countries));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideCountries(): iterable
    {
        yield 'France first, the others in their order, the territories counted' => [
            ['CH' => 'Suisse', 'RE' => 'La Réunion', 'FR' => 'France', 'MQ' => 'Martinique', 'BE' => 'Belgique'],
            "France, Suisse, Belgique et 2 territoires d'Outre-Mer",
        ];
        yield 'a single territory keeps its name' => [['FR' => 'France', 'YT' => 'Mayotte'], 'France et Mayotte'];
        yield 'no territory' => [['FR' => 'France'], 'France'];
        yield 'no France' => [['BE' => 'Belgique', 'GP' => 'Guadeloupe', 'GF' => 'Guyane'], "Belgique et 2 territoires d'Outre-Mer"];
        yield 'no event to come anywhere' => [[], ''];
    }
}
