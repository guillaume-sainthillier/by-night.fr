<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\HtmlFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlFormatterTest extends TestCase
{
    private HtmlFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new HtmlFormatter();
    }

    #[DataProvider('provideLinks')]
    public function testEnsureProtocol(?string $link, ?string $expected): void
    {
        self::assertSame($expected, $this->formatter->ensureProtocol($link));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideLinks(): iterable
    {
        yield 'http url is kept' => ['http://example.org', 'http://example.org'];
        yield 'https url is kept' => ['https://example.org/page?a=1', 'https://example.org/page?a=1'];
        yield 'bare host gets http' => ['www.example.org', 'http://www.example.org'];
        yield 'bare host with a path gets http' => ['example.org/page', 'http://example.org/page'];
    }

    #[DataProvider('provideHrefs')]
    public function testNormalizeHref(string $href, string $expected): void
    {
        self::assertSame($expected, $this->formatter->normalizeHref($href));
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
        yield 'surrounding spaces are trimmed' => ['  www.example.org ', 'https://www.example.org'];
    }

    public function testRewriteAnchorsKeepsOnlyTheNormalizedHref(): void
    {
        self::assertSame(
            'Infos : <a href="https://www.a.fr" target="_blank" rel="nofollow">ici</a> !',
            $this->formatter->rewriteAnchors('Infos : <a class="x" href="www.a.fr" onclick="track()">ici</a> !')
        );
    }

    public function testRewriteAnchorsRewritesEachAnchorOnItsOwn(): void
    {
        self::assertSame(
            '<a href="https://www.a.fr" target="_blank" rel="nofollow">A</a> et <a href="https://www.b.fr" target="_blank" rel="nofollow">B</a>',
            $this->formatter->rewriteAnchors('<a href="www.a.fr">A</a> et <a href=\'www.b.fr\' title="B">B</a>')
        );
    }

    public function testLinkifyUrlsLinksBareUrls(): void
    {
        self::assertSame(
            'Billets sur <a href="https://example.org/billets" target="_blank" rel="nofollow">https://example.org/billets</a> dès demain',
            $this->formatter->linkifyUrls('Billets sur https://example.org/billets dès demain')
        );
    }

    public function testLinkifyUrlsLeavesUrlsInsideTagsAlone(): void
    {
        $html = '<a href="https://example.org">https://example.org</a>';

        self::assertSame($html, $this->formatter->linkifyUrls($html));
    }

    public function testStripUnsafeTagsLeavesHarmlessHtmlUntouched(): void
    {
        $html = '<p>Concert <strong>gratuit</strong></p><iframe src="https://example.org"></iframe>';

        self::assertSame($html, $this->formatter->stripUnsafeTags($html));
    }

    public function testStripUnsafeTagsFiltersTextWithAScript(): void
    {
        $html = $this->formatter->stripUnsafeTags('<p>Concert</p><script>track()</script><iframe></iframe>');

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('<p>Concert</p>', $html);
    }

    public function testFormatChainsEveryStep(): void
    {
        self::assertSame(
            '<p>Site : <a href="https://www.a.fr" target="_blank" rel="nofollow">A</a>, billets sur <a href="https://example.org" target="_blank" rel="nofollow">https://example.org</a></p>',
            $this->formatter->format('<p>Site : <a href="www.a.fr">A</a>, billets sur https://example.org</p>')
        );
    }

    public function testFormatAcceptsNull(): void
    {
        self::assertSame('', $this->formatter->format(null));
    }
}
