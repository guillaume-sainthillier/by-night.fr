<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\StringExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringExtensionTest extends TestCase
{
    #[DataProvider('provideExcerpts')]
    public function testExcerpt(?string $html, int $length, string $expected): void
    {
        self::assertSame($expected, new StringExtension()->excerpt($html, $length));
    }

    /**
     * @return iterable<string, array{string|null, int, string}>
     */
    public static function provideExcerpts(): iterable
    {
        yield 'no description' => [null, 160, ''];
        yield 'tags stripped, entities decoded' => ['<p>&nbsp;DJ SET ELECTRO &amp; HOUSE</p>', 160, 'DJ SET ELECTRO & HOUSE'];
        yield 'whitespace collapsed' => ["<p>Concert</p>\n\n<p>Jazz   manouche</p>", 160, 'Concert Jazz manouche'];
        yield 'paragraphs kept apart' => ['<p>Concert</p><p>Jazz</p><br>Manouche', 160, 'Concert Jazz Manouche'];
        yield 'inline tags joined' => ["L'<em>Opéra</em> de Monte-Carlo", 160, "L'Opéra de Monte-Carlo"];
        yield 'cut between two words, the ellipsis included' => ['Un concert de jazz manouche', 16, 'Un concert de…'];
        yield 'short enough' => ['Un concert', 10, 'Un concert'];
        yield 'a word longer than the excerpt cut inside' => ['Anticonstitutionnellement', 10, 'Anticonst…'];
        yield 'no-break spaces kept' => ["<p>Jazz\u{a0}: un concert\u{202f}!</p>", 160, "Jazz\u{a0}: un concert\u{202f}!"];
        yield 'a no-break space next to a space collapsed' => ['<p>Conflans.&nbsp;</p> <p>Abasourdi</p>', 160, 'Conflans. Abasourdi'];
        yield 'never parts a word from its colon' => ["Un concert de jazz\u{a0}: manouche", 20, 'Un concert de…'];
        yield 'escaped markup stays text' => ['&lt;script&gt;alert(1)&lt;/script&gt;', 160, '<script>alert(1)</script>'];
    }
}
