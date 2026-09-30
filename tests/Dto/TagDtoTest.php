<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Dto;

use App\Dto\TagDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagDtoTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'spaces' => ['  Concert ', 'Concert'];
        yield 'trailing no-break space' => ["Humour\u{A0}", 'Humour'];
        yield 'space then no-break space' => ["Cabaret \u{A0}", 'Cabaret'];
        yield 'leading thin space and tab' => ["\u{2009}\tJazz", 'Jazz'];
        yield 'inner spaces kept' => ["Son\u{A0}et lumière", "Son\u{A0}et lumière"];
    }

    /**
     * 49 tags were created with a trailing no-break space, which the import took for other tags.
     */
    #[DataProvider('provideNames')]
    public function testTheNameIsTrimmedOfEverySpace(string $name, string $expected): void
    {
        self::assertSame($expected, TagDto::fromString($name)->name);
    }
}
