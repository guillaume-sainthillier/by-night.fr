<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\ParseExtension;
use App\Utils\HtmlFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParseExtensionTest extends TestCase
{
    #[DataProvider('provideMarkdown')]
    public function testMarkdown(?string $markdown, string $expected): void
    {
        self::assertSame($expected, new ParseExtension(new HtmlFormatter())->markdown($markdown));
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function provideMarkdown(): iterable
    {
        yield 'no description' => [null, ''];
        yield 'a paragraph' => ["Du Capitole aux quais de la Garonne\u{a0}: concerts & expos.", "<p>Du Capitole aux quais de la Garonne\u{a0}: concerts &amp; expos.</p>"];
        yield 'emphasis and links' => ['Du **Bikini** au [Zénith](https://zenith-toulouse.com)', '<p>Du <strong>Bikini</strong> au <a href="https://zenith-toulouse.com">Zénith</a></p>'];
        yield 'paragraphs' => ["Concerts.\n\nExpos.", "<p>Concerts.</p>\n<p>Expos.</p>"];
        yield 'raw HTML escaped' => ['<script>alert(1)</script>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'];
        yield 'script links neutralised' => ['[clic](javascript:alert(1))', '<p><a href="javascript%3Aalert(1)">clic</a></p>'];
    }
}
