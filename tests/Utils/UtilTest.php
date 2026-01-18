<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Tests\AppKernelTestCase;
use App\Utils\Util;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class UtilTest extends AppKernelTestCase
{
    protected Util $utils;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->utils = static::getContainer()->get(Util::class);
    }

    #[DataProvider('nonNumericCharsProvider')]
    public function testReplaceNonNumericChars(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->replaceNonNumericChars($actual), 'Original : ' . $actual);
    }

    // Latitude or Postal Code
    public static function nonNumericCharsProvider(): array
    {
        return [
            ['31470', '31470'],
            ['31 470', '31470'],
            ['43.6', '43.6'],
            ['43.6😍', '43.6'],
            ['43.6zéça', '43.6'],
            ['43-6zéça', '43-6'],
        ];
    }

    #[DataProvider('nonAlphanumericCharsProvider')]
    public function testReplaceNonAlphanumericChars(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->replaceNonAlphanumericChars($actual), 'Original : ' . $actual);
    }

    public static function nonAlphanumericCharsProvider(): array
    {
        return [
            ['Lorem Ipsum', 'Lorem Ipsum'],
            ['Lorem Ipsum', 'Lorem Ipsum'],
            ['😍 Lorem Ipsum 😍', 'Lorem Ipsum'],
        ];
    }

    #[DataProvider('deleteSpaceBetweenProvider')]
    public function testDeleteSpaceBetween(string $actual, string $expected, string|array $delimiters): void
    {
        self::assertEquals($expected, $this->utils->deleteSpaceBetween($actual, $delimiters), 'Original : ' . $actual);
    }

    public static function deleteSpaceBetweenProvider(): array
    {
        return [
            ['Lorem Ipsum', 'Lorem Ipsum', ''], // Nothing happen
            ['Lorem / Ipsum', 'Lorem/Ipsum', '/'],
            ['Saint-Lys', 'Saint-Lys', ['-']], // Postal String
            ['Saint - Lys', 'Saint-Lys', ['-']], // Postal String
            ['Saint-Lys', 'Saint-Lys', '-'], // Postal String
            ['Saint - Lys', 'Saint-Lys', '-'], // Postal String
        ];
    }

    #[DataProvider('deleteStopWordsProvider')]
    public function testDeleteStopWords(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->deleteStopWords($actual), 'Original : ' . $actual);
    }

    public static function deleteStopWordsProvider(): array
    {
        return [
            ['Bikini', 'Bikini'],
            ['Le bikini', 'bikini'],
            ['Ilôt', 'Ilôt'],
            ['Mon Ilôt', 'Ilôt'],
            ['Sans mot autre', ''],
        ];
    }

    #[DataProvider('deleteMultipleSpacesProvider')]
    public function testDeleteMultipleSpaces(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->deleteMultipleSpaces($actual), 'Original : ' . $actual);
    }

    public static function deleteMultipleSpacesProvider(): array
    {
        return [
            ['My Super Event', 'My Super Event'],
            [' My Super Event ', 'My Super Event'],
            ['  My     Super  Event         ', 'My Super Event'],
            ['  My   😍  Super  Event         ', 'My 😍 Super Event'],
        ];
    }

    #[DataProvider('utf8TitleCaseProvider')]
    public function testUtf8TitleCase(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->utf8TitleCase($actual), 'Original : ' . $actual);
    }

    public static function utf8TitleCaseProvider(): array
    {
        return [
            ['My Super Event', 'My Super Event'],
            ['my super event', 'My Super Event'],
            ['çà super évent😍', 'Çà Super Évent😍'],
        ];
    }

    #[DataProvider('utf8LowerCaseProvider')]
    public function testUtf8LowerCase(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->utf8LowerCase($actual), 'Original : ' . $actual);
    }

    public static function utf8LowerCaseProvider(): array
    {
        return [
            ['my super event', 'my super event'],
            ['My Super Event', 'my super event'],
            ['Çà Super Évent😍', 'çà super évent😍'],
        ];
    }

    #[DataProvider('replaceAccentsProvider')]
    public function testReplaceAccents(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->utils->replaceAccents($actual), 'Original : ' . $actual);
    }

    public static function replaceAccentsProvider(): array
    {
        return [
            ['my super event', 'my super event'],
            ['MÝ ŠúpËr êvéÑT😍', 'MY SupEr eveNT😍'],
        ];
    }
}
