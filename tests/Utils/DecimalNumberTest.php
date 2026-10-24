<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\DecimalNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalNumberTest extends TestCase
{
    #[DataProvider('provideNumbers')]
    public function testANumberReadsWithEitherSeparator(string $number, float $expected): void
    {
        self::assertSame($expected, DecimalNumber::parse($number));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function provideNumbers(): iterable
    {
        yield 'a decimal comma' => ['27,50', 27.5];
        yield 'a decimal point' => ['12.5', 12.5];
        yield 'the zeros of a feed' => ['22.0000', 22.0];
        yield 'a whole number' => ['39', 39.0];
    }

    #[DataProvider('provideValues')]
    public function testAValueReadsWhateverItsShape(mixed $value, ?float $expected): void
    {
        self::assertSame($expected, DecimalNumber::fromValue($value));
    }

    /**
     * @return iterable<string, array{mixed, ?float}>
     */
    public static function provideValues(): iterable
    {
        yield 'a float of a JSON feed' => [43.6047, 43.6047];
        yield 'an integer' => [1, 1.0];
        yield 'a decimal point' => ['43.6047', 43.6047];
        yield 'a decimal comma' => ['43,6047', 43.6047];
        yield 'a negative longitude' => ['-1,5536', -1.5536];
        yield 'spaces around it' => [' 1.47 ', 1.47];
        yield 'zero stays a number' => ['0', 0.0];
        yield 'no value' => [null, null];
        yield 'an empty column' => ['', null];
        yield 'a word' => ['inconnu', null];
        yield 'a unit after it' => ['43.6°', null];
        yield 'a thousands separator and a decimal one' => ['1,234.5', null];
    }
}
