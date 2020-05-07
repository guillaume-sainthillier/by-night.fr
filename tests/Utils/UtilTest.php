<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Tests\ContainerTestCase;
use App\Utils\Util;

class UtilTest extends ContainerTestCase
{
    /**
     * @var Util
     */
    protected $utils;

    protected function setUp(): void
    {
        parent::setUp();

        $this->utils = static::$container->get(Util::class);
    }

    /**
     * @dataProvider nonNumericCharsProvider
     */
    public function testReplaceNonNumericChars($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->replaceNonNumericChars($actual), 'Original : ' . $actual);
    }

    //Latitude or Postal Code
    public function nonNumericCharsProvider()
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

    /**
     * @dataProvider nonAlphanumericCharsProvider
     */
    public function testReplaceNonAlphanumericChars($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->replaceNonAlphanumericChars($actual), 'Original : ' . $actual);
    }

    public function nonAlphanumericCharsProvider()
    {
        return [
            ['Lorem Ipsum', 'Lorem Ipsum'],
            ['Lorem Ipsum', 'Lorem Ipsum'],
            ['😍 Lorem Ipsum 😍', 'Lorem Ipsum'],
        ];
    }

    /**
     * @dataProvider deleteSpaceBetweenProvider
     */
    public function testDeleteSpaceBetween($actual, $expected, $delimiters)
    {
        $this->assertEquals($expected, $this->utils->deleteSpaceBetween($actual, $delimiters), 'Original : ' . $actual);
    }

    public function deleteSpaceBetweenProvider()
    {
        return [
            ['Lorem Ipsum', 'Lorem Ipsum', ''], //Nothing happen
            ['Lorem / Ipsum', 'Lorem/Ipsum', '/'],
            ['Saint-Lys', 'Saint-Lys', ['-']], //Postal String
            ['Saint - Lys', 'Saint-Lys', ['-']], //Postal String
            ['Saint-Lys', 'Saint-Lys', '-'], //Postal String
            ['Saint - Lys', 'Saint-Lys', '-'], //Postal String
        ];
    }

    /**
     * @dataProvider deleteStopWordsProvider
     */
    public function testDeleteStopWords($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->deleteStopWords($actual), 'Original : ' . $actual);
    }

    public function deleteStopWordsProvider()
    {
        return [
            ['Bikini', 'Bikini'],
            ['Le bikini', 'bikini'],
            ['Ilôt', 'Ilôt'],
            ['Mon Ilôt', 'Ilôt'],
            ['Sans mot autre', ''],
        ];
    }

    /**
     * @dataProvider deleteMultipleSpacesProvider
     */
    public function testDeleteMultipleSpaces($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->deleteMultipleSpaces($actual), 'Original : ' . $actual);
    }

    public function deleteMultipleSpacesProvider()
    {
        return [
            ['My Super Event', 'My Super Event'],
            [' My Super Event ', 'My Super Event'],
            ['  My     Super  Event         ', 'My Super Event'],
            ['  My   😍  Super  Event         ', 'My 😍 Super Event'],
        ];
    }

    /**
     * @dataProvider utf8TitleCaseProvider
     */
    public function testUtf8TitleCase($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->utf8TitleCase($actual), 'Original : ' . $actual);
    }

    public function utf8TitleCaseProvider()
    {
        return [
            ['My Super Event', 'My Super Event'],
            ['my super event', 'My Super Event'],
            ['çà super évent😍', 'Çà Super Évent😍'],
        ];
    }

    /**
     * @dataProvider utf8LowerCaseProvider
     */
    public function testUtf8LowerCase($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->utf8LowerCase($actual), 'Original : ' . $actual);
    }

    public function utf8LowerCaseProvider()
    {
        return [
            ['my super event', 'my super event'],
            ['My Super Event', 'my super event'],
            ['Çà Super Évent😍', 'çà super évent😍'],
        ];
    }

    /**
     * @dataProvider replaceAccentsProvider
     */
    public function testReplaceAccents($actual, $expected)
    {
        $this->assertEquals($expected, $this->utils->replaceAccents($actual), 'Original : ' . $actual);
    }

    public function replaceAccentsProvider()
    {
        return [
            ['my super event', 'my super event'],
            ['MÝ ŠúpËr êvéÑT😍', 'MY SupEr eveNT😍'],
        ];
    }
}
