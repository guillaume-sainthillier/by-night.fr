<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

/**
 * Prepares the free text shipped by feeds or typed by members (event descriptions, comments, websites)
 * for rendering: links get an absolute target and open in a new tab, bare URLs become links.
 */
final class HtmlFormatter
{
    private const string ALLOWED_TAGS = '<a><abbr><acronym><address><article><aside><b><bdo><big><blockquote><br><caption><cite><code><col><colgroup><dd><del><details><dfn><div><dl><dt><em><figcaption><figure><font><h1><h2><h3><h4><h5><h6><hgroup><hr><i><img><ins><li><map><mark><menu><meter><ol><p><pre><q><rp><rt><ruby><s><samp><section><small><span><strong><style><sub><summary><sup><table><tbody><td><tfoot><th><thead><time><tr><tt><u><ul><var><wbr>';

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
        return $this->stripUnsafeTags($this->linkifyUrls($this->rewriteAnchors((string) $html)));
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
     * Bare URLs at the start of a line or after a space become links; the ones already inside a tag are left alone.
     */
    public function linkifyUrls(string $text): string
    {
        return (string) preg_replace("#(^|[\n ])((http|https|ftp)://)?([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1<a href="\\4" target="_blank" rel="nofollow">\\4</a>', $text);
    }

    /**
     * Only text mentioning script, style or link tags is filtered down to the allowed tags.
     */
    public function stripUnsafeTags(string $html): string
    {
        if (!preg_match('#<(.*)(script|style|link)#i', $html)) {
            return $html;
        }

        return strip_tags($html, self::ALLOWED_TAGS);
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
