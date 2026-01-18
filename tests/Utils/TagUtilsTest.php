<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\TagUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TagUtilsTest extends TestCase
{
    #[DataProvider('tagTermsProvider')]
    public function testGetTagTerms(string $input, array $expected): void
    {
        $result = TagUtils::getTagTerms($input);

        self::assertEquals($expected, $result);
    }

    public static function tagTermsProvider(): array
    {
        return [
            // Single tag
            ['rock', ['rock']],

            // Comma separated
            ['rock,jazz,blues', ['rock', 'jazz', 'blues']],

            // Slash separated
            ['rock/jazz/blues', ['rock', 'jazz', 'blues']],

            // Mixed delimiters
            ['rock,jazz/blues', ['rock', 'jazz', 'blues']],

            // With spaces
            ['rock , jazz , blues', ['rock', 'jazz', 'blues']],
            ['rock / jazz / blues', ['rock', 'jazz', 'blues']],

            // Duplicates removed
            ['rock,rock,jazz', ['rock', 'jazz']],

            // Empty strings filtered
            ['rock,,jazz', ['rock', 'jazz']],
            ['rock,,,jazz', ['rock', 'jazz']],

            // Empty input
            ['', []],
            [',,,', []],

            // Complex example
            ['rock, jazz, blues / rock / pop', ['rock', 'jazz', 'blues', 'pop']],
        ];
    }

    public function testGetTagTermsTrimsWhitespace(): void
    {
        $result = TagUtils::getTagTerms('  rock  ,  jazz  ');

        self::assertContains('rock', $result);
        self::assertContains('jazz', $result);
        self::assertNotContains('  rock  ', $result);
    }

    public function testGetTagTermsReturnsIndexedArray(): void
    {
        $result = TagUtils::getTagTerms('rock,jazz,blues');

        self::assertArrayHasKey(0, $result);
        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(2, $result);
        self::assertEquals(array_values($result), $result);
    }

    public function testGetTagTermsPreservesOrder(): void
    {
        $result = TagUtils::getTagTerms('rock,jazz,blues');

        self::assertEquals('rock', $result[0]);
        self::assertEquals('jazz', $result[1]);
        self::assertEquals('blues', $result[2]);
    }

    public function testGetTagTermsWithSpecialCharacters(): void
    {
        $result = TagUtils::getTagTerms("rock&roll,hip-hop,drum'n'bass");

        self::assertContains('rock&roll', $result);
        self::assertContains('hip-hop', $result);
        self::assertContains("drum'n'bass", $result);
    }

    public function testGetTagTermsWithUnicode(): void
    {
        $result = TagUtils::getTagTerms('café,thé,français');

        self::assertContains('café', $result);
        self::assertContains('thé', $result);
        self::assertContains('français', $result);
    }

    public function testGetTagTermsWithOnlyDelimiters(): void
    {
        $result = TagUtils::getTagTerms(',/,/,');

        self::assertEmpty($result);
    }

    public function testGetTagTermsWithConsecutiveDelimiters(): void
    {
        $result = TagUtils::getTagTerms('rock,,jazz//blues');

        self::assertContains('rock', $result);
        self::assertContains('jazz', $result);
        self::assertContains('blues', $result);
        self::assertCount(3, $result);
    }
}
