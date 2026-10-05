<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\LenientJson;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LenientJsonTest extends TestCase
{
    public static function provideStrings(): iterable
    {
        yield 'a lone high surrogate' => ['"Concert \ud83d"', "Concert \u{FFFD}"];
        yield 'a lone low surrogate' => ['"\ude00 Concert"', "\u{FFFD} Concert"];
        yield 'a high surrogate followed by another high one' => ['"\ud83d\ud83d\ude00"', "\u{FFFD}😀"];
        yield 'a whole pair' => ['"\ud83c\udf89 F\u00eate"', '🎉 Fête'];
        yield 'an escaped backslash before "ud83d"' => ['"C:\\\\ud83d"', 'C:\ud83d'];
        yield 'an escaped backslash before a lone surrogate' => ['"\\\\\ud83d"', "\\\u{FFFD}"];
        yield 'uppercase hex digits' => ['"\uD83D!"', "\u{FFFD}!"];
    }

    #[DataProvider('provideStrings')]
    public function testLoneSurrogatesAreReplaced(string $json, string $expected): void
    {
        self::assertSame(['text' => $expected], LenientJson::decode('{"text":' . $json . '}'));
    }

    public function testInvalidJsonStillFails(): void
    {
        $this->expectException(JsonException::class);

        LenientJson::decode('{"text":');
    }

    public function testAScalarDocumentFails(): void
    {
        $this->expectException(JsonException::class);

        LenientJson::decode('"text"');
    }
}
