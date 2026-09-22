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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParseExtensionTest extends TestCase
{
    #[DataProvider('provideHrefs')]
    public function testLinksInDescriptionsGetAnAbsoluteTarget(string $href, string $expected): void
    {
        $html = new ParseExtension()->parseTags(\sprintf('Infos : <a class="x" href="%s">ici</a> !', $href));

        self::assertSame(\sprintf('Infos : <a href="%s" target="_blank" rel="nofollow">ici</a> !', $expected), $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideHrefs(): iterable
    {
        yield 'http url is kept' => ['http://example.org/page', 'http://example.org/page'];
        yield 'https url is kept' => ['https://example.org/page?a=1&b=2', 'https://example.org/page?a=1&b=2'];
        yield 'mailto is kept' => ['mailto:contact@example.org', 'mailto:contact@example.org'];
        yield 'anchor is kept' => ['#programme', '#programme'];
        yield 'site path is kept' => ['/paris/agenda', '/paris/agenda'];
        yield 'protocol-relative url gets https' => ['//example.org/page', 'https://example.org/page'];
        yield 'bare www host gets https' => ['www.museum-bordeaux.fr', 'https://www.museum-bordeaux.fr'];
        yield 'uppercase www host gets https' => ['WWW.coriacecompagnie.com', 'https://WWW.coriacecompagnie.com'];
        yield 'bare host with a path gets https' => ['icam.link/visitevirtuelle-6sites', 'https://icam.link/visitevirtuelle-6sites'];
        yield 'e-mail address becomes mailto' => ['info@lecteurduval.org', 'mailto:info@lecteurduval.org'];
        yield 'markdown link is unwrapped' => ['[https://www.youtube.com/@MANTISBDK](https://www.youtube.com/@MANTISBDK)', 'https://www.youtube.com/@MANTISBDK'];
    }

    public function testEachAnchorIsRewrittenOnItsOwn(): void
    {
        $html = new ParseExtension()->parseTags('<a href="www.a.fr">A</a> et <a href=\'www.b.fr\' title="B">B</a>');

        self::assertSame(
            '<a href="https://www.a.fr" target="_blank" rel="nofollow">A</a> et <a href="https://www.b.fr" target="_blank" rel="nofollow">B</a>',
            $html
        );
    }

    public function testBareUrlsInTextAreStillLinked(): void
    {
        $html = new ParseExtension()->parseTags('Billets sur https://example.org/billets dès demain');

        self::assertSame('Billets sur <a href="https://example.org/billets" target="_blank" rel="nofollow">https://example.org/billets</a> dès demain', $html);
    }
}
