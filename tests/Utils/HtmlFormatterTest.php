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
        yield 'https url is kept' => ['https://example.org/page?a=1#top', 'https://example.org/page?a=1#top'];
        yield 'uppercase scheme is lowered' => ['Http://acdcu.free.fr', 'http://acdcu.free.fr'];
        yield 'surrounding spaces are trimmed' => [" \u{a0}https://theatre-sorano.fr ", 'https://theatre-sorano.fr'];
        yield 'bare host gets https' => ['www.toulouse-tourisme.com', 'https://www.toulouse-tourisme.com'];
        yield 'bare host with a path gets https' => ['resavacances.toulouse-tourisme.com/fr/evenements', 'https://resavacances.toulouse-tourisme.com/fr/evenements'];
        yield 'bare host with a port gets https' => ['example.org:8080/billets', 'https://example.org:8080/billets'];
        yield 'uppercase host is kept' => ['www.SaintRaymond.toulouse.fr', 'https://www.SaintRaymond.toulouse.fr'];
        yield 'accented host gets https' => ['www.labeautébio.com', 'https://www.labeautébio.com'];
        yield 'host with a trailing dot is kept' => ['https://astucedamourpuissant.webnode.fr.', 'https://astucedamourpuissant.webnode.fr.'];
        yield 'host starting with http gets https' => ['httpbin.org/get', 'https://httpbin.org/get'];
        yield 'host starting with ftp gets https' => ['ftp.example.org', 'https://ftp.example.org'];
        yield 'protocol-relative url gets https' => ['//bit.ly/41q6H38', 'https://bit.ly/41q6H38'];
        yield 'stray leading slash is dropped' => ['/indiv.themisweb.fr/0079/fChoixSeance.aspx?idstructure=0079', 'https://indiv.themisweb.fr/0079/fChoixSeance.aspx?idstructure=0079'];
        yield 'markdown link is unwrapped' => ['[billets](https://example.org/billets)', 'https://example.org/billets'];
        yield 'e-mail becomes mailto' => ['reservation@ringsceneperipherique.com', 'mailto:reservation@ringsceneperipherique.com'];
        yield 'mailto is kept' => ['mailto:contact@example.org', 'mailto:contact@example.org'];
        yield 'mailto slashes are dropped' => ['mailto://bibliotheques@univ-paris13.fr', 'mailto:bibliotheques@univ-paris13.fr'];
        yield 'url behind mailto is recovered' => ['mailto:https://www.imagesonore.net/billetterie.html', 'https://www.imagesonore.net/billetterie.html'];
        yield 'spaces in the query are encoded' => ['www.ardei-soft.com/tournefeuille/spectacle.html?spectacle=La Dispute', 'https://www.ardei-soft.com/tournefeuille/spectacle.html?spectacle=La%20Dispute'];
        yield 'spaces in a full url query are encoded' => ['https://my.weezevent.com/fetons?utm_campaign=Novembre%20 Dcembre 2025&utm_medium=email', 'https://my.weezevent.com/fetons?utm_campaign=Novembre%20%20Dcembre%202025&utm_medium=email'];
        yield 'e-mail behind a scheme becomes mailto' => ['http://animation.nature@ccpbs.fr/', 'mailto:animation.nature@ccpbs.fr'];
        yield 'mistyped scheme is repaired' => ['htpps://www.instagram.com/cyclonesmag', 'https://www.instagram.com/cyclonesmag'];
        yield 'truncated scheme is repaired' => ['ttps://bit.ly/3ONMuwN', 'https://bit.ly/3ONMuwN'];
        yield 'doubled scheme is repaired' => ['http://https//philomania.fr', 'https://philomania.fr'];
        yield 'doubled scheme with a colon is repaired' => ['http://https:/lebalzac.fr', 'https://lebalzac.fr'];
        yield 'doubled scheme behind www is repaired' => ['https://www.https://www.abbayedeboquen.fr/', 'https://www.abbayedeboquen.fr/'];
        yield 'zero-width space is dropped' => ["http://\u{200b}www.60adada.org", 'http://www.60adada.org'];
        yield 'soft hyphens are dropped' => ["www.mairie-tour\u{ad}ne\u{ad}feuille.fr", 'https://www.mairie-tournefeuille.fr'];
        yield 'tel is kept' => ['tel:+33561000000', 'tel:+33561000000'];
        yield 'null' => [null, null];
        yield 'empty' => ['  ', null];
        yield 'several urls in one field' => ['www.abc-toulouse.fr www.fifigrot.com', null];
        yield 'url followed by another one' => ['https://example.org/a https://example.org/b', null];
        yield 'url followed by some text' => ['https://web.digitick.com/ez3kiel.html Etienne ANDRE', null];
        yield 'text behind a scheme' => ['http://à venir', null];
        yield 'word behind a scheme' => ['http://Gamelle', null];
        yield 'phone number' => ['92.05.40.65', null];
        yield 'handle behind a scheme' => ['http://@gmail.com', null];
        yield 'phone number behind a scheme' => ['http://01.45.18.20', null];
        yield 'ftp url' => ['ftp://example.org/file', null];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'data uri' => ['data:text/html,<script>alert(1)</script>', null];
        yield 'path without a host' => ['sortir-a-toulouse/agenda-sorties-toulouse', null];
        yield 'host without a tld' => ['localhost', null];
        yield 'scheme without a host' => ['https://', null];
    }

    #[DataProvider('provideHrefs')]
    public function testNormalizeHref(string $href, ?string $expected): void
    {
        self::assertSame($expected, $this->formatter->normalizeHref($href));
    }

    /**
     * @return iterable<string, array{string, ?string}>
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
        yield 'empty href is kept' => ['', ''];
        yield 'javascript is dropped' => ['javascript:alert(1)', null];
        yield 'path without a host is dropped' => ['sortir-a-toulouse/agenda', null];
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

    public function testRewriteAnchorsTurnsAnUnusableHrefIntoPlainText(): void
    {
        self::assertSame(
            'Voir <a>ici</a>',
            $this->formatter->rewriteAnchors('Voir <a href="javascript:alert(1)" class="x">ici</a>')
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
