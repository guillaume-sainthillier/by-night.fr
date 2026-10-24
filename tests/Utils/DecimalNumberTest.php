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
}
