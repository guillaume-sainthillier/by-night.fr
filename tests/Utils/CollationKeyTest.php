<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\CollationKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pairs checked against MySQL 8 (SELECT a = b COLLATE utf8mb4_unicode_ci).
 */
final class CollationKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providePairs(): iterable
    {
        yield 'case' => ['Concert', 'CONCERT', true];
        yield 'accents' => ['Théâtre', 'theatre', true];
        yield 'sharp s' => ['Straße', 'Strasse', true];
        yield 'ligature' => ['œuvre', 'oeuvre', true];
        yield 'trailing spaces' => ['Concert  ', 'Concert', true];
        yield 'emoji beyond the BMP' => ['🎸', '🎺', true];
        yield 'another word' => ['Jazz', 'Jaz', false];
        yield 'inner spaces count' => ['a b', 'a  b', false];
        yield 'hyphen is not a space' => ['Rock-n-roll', 'Rock n roll', false];
    }

    #[DataProvider('providePairs')]
    public function testTheKeyAgreesWithTheCollation(string $left, string $right, bool $equal): void
    {
        self::assertSame($equal, CollationKey::of($left) === CollationKey::of($right));
    }
}
