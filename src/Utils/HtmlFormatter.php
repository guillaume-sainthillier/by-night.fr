<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Prepares the free text shipped by feeds or typed by members (event descriptions, comments, websites)
 * for rendering: links get an absolute target and open in a new tab, bare URLs become links, and
 * the markup is reduced to formatting that cannot run script.
 */
final class HtmlFormatter
{
    /**
     * Formatting kept as is, with the attributes of FORMATTING_ATTRIBUTES.
     */
    private const array FORMATTING_ELEMENTS = [
        'abbr', 'address', 'article', 'aside', 'b', 'bdo', 'blockquote', 'br', 'caption', 'cite', 'code', 'col', 'colgroup',
        'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hgroup', 'hr', 'i', 'ins', 'li', 'mark', 'ol', 'p', 'pre', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'small',
        'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'tfoot', 'thead', 'time', 'tr', 'u', 'ul', 'var', 'wbr',
    ];

    private const array FORMATTING_ATTRIBUTES = ['title', 'dir', 'lang'];

    /**
     * Elements carrying a link, a media or a table layout, with the attributes they keep.
     */
    private const array ELEMENTS_WITH_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'iframe' => ['src', 'title', 'width', 'height', 'frameborder', 'allowfullscreen'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /**
     * Removed with their content: code, styles and the settings Word pastes along with the text.
     * Any other element missing from the lists above only loses its tag (<font>, <center>, <o:p>…).
     */
    private const array DROPPED_ELEMENTS = [
        'script', 'noscript', 'template', 'xml', 'svg', 'math', 'object', 'embed', 'applet', 'frameset', 'frame', 'base',
    ];

    private ?HtmlSanitizerInterface $sanitizer = null;

    /**
     * A host, optionally with a port and a path: "www.example.org", "théâtre.fr:8080/billets?id=1".
     */
    private const string BARE_HOST = '~^(?:[\p{L}\p{N}_-]+\.)+\p{L}{2,}\.?(?::\d+)?(?:[/?#]\S*)?$~u';

    /**
     * What gets typed in front of a host: "http://", "Https://", "//", a stray "/", but also a scheme
     * mistyped or typed twice ("htpps://", "ttps://", "http://https//", "https://www.https://").
     */
    private const string SCHEME_PREFIX = '~^(?:(?:www\.)?(?:h?t{0,3}p{0,2}s?|hppts)[:.;]?[/\\\\]{1,2}:?)+~i';

    /**
     * Turns a link as feeds and members type it ("www.example.org", "Http://…", "contact@example.org",
     * "mailto://…", "//cdn…") into an absolute URL, or null when it can't be linked safely (several
     * URLs in one field, "javascript:", "ftp:", a path without a host, a phone number).
     */
    public function ensureProtocol(?string $link): ?string
    {
        // Invisible characters (zero-width spaces, soft hyphens) get pasted along with the link
        $link = mb_trim((string) preg_replace('~\p{Cf}~u', '', (string) $link));
        if (1 === preg_match('~^\[[^\]]*\]\((.+)\)$~', $link, $matches)) {
            // Markdown: [label](url)
            $link = mb_trim($matches[1]);
        }

        // Spaces left in a query string ("?spectacle=La Dispute") belong to the URL; anywhere
        // else they separate several links or some text.
        $link = (string) preg_replace_callback('~\?.*$~s', static fn (array $matches): string => (string) preg_replace('~\s~u', '%20', $matches[0]), $link);

        if (1 === preg_match('~^mailto:/*(.+)$~i', $link, $matches)) {
            // Whatever follows is sometimes not an e-mail at all ("mailto:https://…")
            return $this->ensureProtocol($matches[1]);
        }

        if (1 === preg_match('~^tel:\S+$~i', $link)) {
            return $link;
        }

        $prefix = 1 === preg_match(self::SCHEME_PREFIX, $link, $matches) ? $matches[0] : '';
        $address = substr($link, \strlen($prefix));

        // "http://contact@example.org/" is an e-mail too
        if (false !== filter_var(rtrim($address, '/'), \FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . rtrim($address, '/');
        }

        if (1 !== preg_match(self::BARE_HOST, $address)) {
            return null;
        }

        // Only a well-typed "http://" stays on http
        return (0 === strcasecmp($prefix, 'http://') ? 'http://' : 'https://') . $address;
    }

    public function format(?string $html): string
    {
        return $this->sanitize($this->linkifyUrls($this->rewriteAnchors((string) $html)));
    }

    /**
     * Every <a> tag is rebuilt from its href alone, as a nofollow link opening in a new tab; an href
     * that can't be linked safely leaves a bare <a>, rendered as plain text.
     */
    public function rewriteAnchors(string $html): string
    {
        return (string) preg_replace_callback(
            '#<a\b[^>]*?href=[\'"]([^\'"]*)[\'"][^>]*>#i',
            function (array $matches): string {
                $href = $this->normalizeHref($matches[1]);

                return null === $href ? '<a>' : \sprintf('<a href="%s" target="_blank" rel="nofollow">', $href);
            },
            $html
        );
    }

    /**
     * Bare http(s) and ftp URLs at the start of a line or after a space become links; the ones already
     * inside a tag are left alone, and other schemes ("javascript://…" typed in a comment) stay text.
     */
    public function linkifyUrls(string $text): string
    {
        return (string) preg_replace("#(^|[\n ])((http|https|ftp)://)?((?:https?|ftp)://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1<a href="\\4" target="_blank" rel="nofollow">\\4</a>', $text);
    }

    /**
     * Keeps the formatting of the allow-lists above and drops everything able to run script: event
     * handlers, javascript: URLs, <script>, <svg>, <style> blocks, frames outside known players.
     */
    public function sanitize(string $html): string
    {
        // The sanitizer only drops body elements: a <style> or <title> would lose its tag but show its text
        $html = (string) preg_replace('~<(style|title)\b[^>]*>.*?(?:</\1\s*>|$)~is', '', $html);
        $html = $this->getSanitizer()->sanitize($html);

        // A frame whose source was refused has nothing left to show
        return (string) preg_replace('~<iframe(?![^>]*\ssrc=)[^>]*>.*?</iframe>~is', '', $html);
    }

    private function getSanitizer(): HtmlSanitizerInterface
    {
        if (null !== $this->sanitizer) {
            return $this->sanitizer;
        }

        $config = new HtmlSanitizerConfig()
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->withAttributeSanitizer(new MediaSourceSanitizer())
            // Descriptions are TEXT columns: nothing is cut at the default 20 000 bytes
            ->withMaxInputLength(-1);

        foreach (self::FORMATTING_ELEMENTS as $element) {
            $config = $config->allowElement($element, self::FORMATTING_ATTRIBUTES);
        }

        foreach (self::ELEMENTS_WITH_ATTRIBUTES as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::DROPPED_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Feeds ship links without a scheme ("www.museum-bordeaux.fr", "info@example.org") or wrapped
     * in Markdown; rendered as-is they resolve relative to the event page and end up as 404s.
     * Anchors and paths of this site are the only relative links kept.
     */
    public function normalizeHref(string $href): ?string
    {
        $href = mb_trim($href);
        if ('' === $href || str_starts_with($href, '#') || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) {
            return $href;
        }

        return $this->ensureProtocol($href);
    }
}
