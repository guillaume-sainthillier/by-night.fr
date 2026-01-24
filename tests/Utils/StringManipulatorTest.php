<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\StringManipulator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringManipulatorTest extends TestCase
{
    #[DataProvider('multipleSpacesProvider')]
    public function testDeleteMultipleSpaces(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->deleteMultipleSpaces()->toString();

        self::assertEquals($expected, $result);
    }

    public static function multipleSpacesProvider(): array
    {
        return [
            ['Hello    World', 'Hello World'],
            ['Too   many    spaces', 'Too many spaces'],
            ['  Leading and trailing  ', 'Leading and trailing'],
            ['Normal spacing', 'Normal spacing'],
        ];
    }

    #[DataProvider('stopWordsProvider')]
    public function testDeleteStopWords(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->deleteStopWords()->deleteMultipleSpaces()->toString();

        self::assertEquals(trim($expected), trim($result));
    }

    public static function stopWordsProvider(): array
    {
        return [
            ['This is a test', 'test'],
            ['The quick brown fox', 'quick brown fox'],
            ['Concert de musique', 'Concert de musique'], // 'de' is part of word boundary
            ['Le grand spectacle', 'grand spectacle'],
        ];
    }

    #[DataProvider('spaceBetweenProvider')]
    public function testDeleteMultipleSpacesBetween(string $input, string $delimiter, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->deleteMultipleSpacesBetween($delimiter)->toString();

        self::assertEquals($expected, $result);
    }

    public static function spaceBetweenProvider(): array
    {
        return [
            ['Saint - Germain - des - Prés', '-', 'Saint-Germain-des-Prés'],
            ['Word  -  Another', '-', 'Word-Another'],
            ['No change here', '-', 'No change here'],
        ];
    }

    #[DataProvider('accentsProvider')]
    public function testReplaceAccents(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->replaceAccents()->toString();

        self::assertEquals($expected, $result);
    }

    public static function accentsProvider(): array
    {
        return [
            ['café', 'cafe'],
            ['élève', 'eleve'],
            ['Zürich', 'Zurich'],
            ['São Paulo', 'Sao Paulo'],
            ['Tête-à-tête', 'Tete-a-tete'],
        ];
    }

    #[DataProvider('nonNumericProvider')]
    public function testNonNumericChars(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->nonNumericChars()->toString();

        self::assertEquals($expected, $result);
    }

    public static function nonNumericProvider(): array
    {
        return [
            ['123abc456', '123456'],
            ['$1,234.56', '1234.56'],
            ['-42.5', '-42.5'],
            ['No numbers here', ''],
            ['43.604652', '43.604652'],
        ];
    }

    #[DataProvider('nonAlphanumericProvider')]
    public function testNonAlphanumericChars(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->nonAlphanumericChars()->toString();

        self::assertEquals($expected, $result);
    }

    public static function nonAlphanumericProvider(): array
    {
        return [
            ['Hello, World!', 'Hello World'],
            ['user@email.com', 'useremailcom'],
            ['abc-123', 'abc123'],
            ['Test_Case', 'TestCase'],
        ];
    }

    #[DataProvider('titleCaseProvider')]
    public function testTitleCase(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->titleCase()->toString();

        self::assertEquals($expected, $result);
    }

    public static function titleCaseProvider(): array
    {
        return [
            ['hello world', 'Hello World'],
            ['UPPERCASE TEXT', 'UPPERCASE TEXT'], // Already uppercase, stays uppercase
            ['MixedCase Text', 'MixedCase Text'], // Title case preserves existing case patterns
        ];
    }

    #[DataProvider('lowerCaseProvider')]
    public function testLowerCase(string $input, string $expected): void
    {
        $manipulator = new StringManipulator($input);
        $result = $manipulator->lowerCase()->toString();

        self::assertEquals($expected, $result);
    }

    public static function lowerCaseProvider(): array
    {
        return [
            ['HELLO WORLD', 'hello world'],
            ['MixedCase', 'mixedcase'],
            ['already lower', 'already lower'],
        ];
    }

    public function testChainedOperations(): void
    {
        $input = '  Café   Concert  -  Jazz  ';
        $manipulator = new StringManipulator($input);

        $result = $manipulator
            ->deleteMultipleSpaces()
            ->deleteMultipleSpacesBetween('-')
            ->replaceAccents()
            ->toString();

        self::assertEquals('Cafe Concert-Jazz', $result);
    }
}
