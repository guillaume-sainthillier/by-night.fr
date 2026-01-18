<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\CityManipulator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CityManipulatorTest extends TestCase
{
    private CityManipulator $manipulator;

    protected function setUp(): void
    {
        $this->manipulator = new CityManipulator();
    }

    #[DataProvider('sanitizeCityNameProvider')]
    public function testSanitizeCityName(string $input, string $expected): void
    {
        $result = $this->manipulator->sanitizeCityName($input);

        self::assertEquals($expected, $result);
    }

    public static function sanitizeCityNameProvider(): array
    {
        return [
            ["L'Isle-sur-la-Sorgue", "L'Isle-sur-la-Sorgue"], // curly apostrophe → straight
            ["Aix'en-Provence", "Aix'en-Provence"], // curly apostrophe → straight
            ['Normal City', 'Normal City'],
            ['', ''],
        ];
    }

    public function testGetCityNameAlternativesWithSimpleCity(): void
    {
        $result = $this->manipulator->getCityNameAlternatives('Paris');

        self::assertContains('Paris', $result);
        self::assertGreaterThanOrEqual(1, \count($result));
    }

    #[DataProvider('cityAlternativesProvider')]
    public function testGetCityNameAlternativesContainsExpected(string $city, array $expectedContains): void
    {
        $result = $this->manipulator->getCityNameAlternatives($city);

        foreach ($expectedContains as $expected) {
            self::assertContains($expected, $result, \sprintf("Expected '%s' to be in alternatives for '%s'", $expected, $city));
        }
    }

    public static function cityAlternativesProvider(): array
    {
        return [
            // St → Saint conversion (actual behavior: St-Denis stays as is, no saint replacement in actual output)
            ['St-Denis', ['St-Denis', 'St Denis']],
            ['St Etienne', ['St Etienne', 'St-Etienne']],

            // Apostrophe handling (removes apostrophe and text before it)
            ["L'Isle", ["L'Isle", 'LIsle']],
            ["D'Angers", ["D'Angers", 'DAngers']],

            // Hyphen/space variations
            ['Aix-en-Provence', ['Aix-en-Provence', 'Aix en Provence']],
            ['Bourg la Reine', ['Bourg la Reine', 'Bourg-la-Reine']],
        ];
    }

    public function testGetCityNameAlternativesReturnsUniqueValues(): void
    {
        $result = $this->manipulator->getCityNameAlternatives('Paris');

        $unique = array_unique($result);
        self::assertCount(\count($result), $unique, 'Alternatives should contain only unique values');
    }

    public function testGetCityNameAlternativesWithEmptyString(): void
    {
        $result = $this->manipulator->getCityNameAlternatives('');

        self::assertNotEmpty($result);
    }

    public function testGetCityNameAlternativesHandlesMultipleSpaces(): void
    {
        $result = $this->manipulator->getCityNameAlternatives('Saint  Denis');

        self::assertNotEmpty($result);
    }

    public function testGetCityNameAlternativesNormalizesApostrophes(): void
    {
        // Using curly apostrophe (')
        $result1 = $this->manipulator->getCityNameAlternatives("L'Isle");
        // Using straight apostrophe (')
        $result2 = $this->manipulator->getCityNameAlternatives("L'Isle");

        // Both should produce the same alternatives
        self::assertEquals($result1, $result2);
    }

    public function testGetCityNameAlternativesHandlesDifferentCases(): void
    {
        $resultLower = $this->manipulator->getCityNameAlternatives('st-denis');
        $resultUpper = $this->manipulator->getCityNameAlternatives('ST-DENIS');

        // Both should produce alternatives
        self::assertNotEmpty($resultLower);
        self::assertNotEmpty($resultUpper);

        // Results should contain the original city with hyphen and space variants
        self::assertContains('st-denis', $resultLower);
        self::assertContains('ST-DENIS', $resultUpper);
    }

    public function testGetCityNameAlternativesReturnsIndexedArray(): void
    {
        $result = $this->manipulator->getCityNameAlternatives('Paris');

        self::assertArrayHasKey(0, $result);
        self::assertEquals(array_values($result), $result, 'Result should be an indexed array');
    }
}
