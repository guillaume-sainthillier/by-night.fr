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

    public function ensureProtocol(?string $link): ?string
    {
        if (!preg_match('#^(http|https|ftp)#', (string) $link)) {
            return 'http://' . $link;
        }

        return $link;
    }

    public function format(?string $html): string
    {
        return $this->stripUnsafeTags($this->linkifyUrls($this->rewriteAnchors((string) $html)));
    }

    /**
     * Every <a> tag is rebuilt from its href alone, as a nofollow link opening in a new tab.
     */
    public function rewriteAnchors(string $html): string
    {
        return (string) preg_replace_callback(
            '#<a\b[^>]*?href=[\'"]([^\'"]*)[\'"][^>]*>#i',
            fn (array $matches): string => \sprintf('<a href="%s" target="_blank" rel="nofollow">', $this->normalizeHref($matches[1])),
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
     */
    public function normalizeHref(string $href): string
    {
        $href = trim($href);
        if (1 === preg_match('#^\[[^\]]*\]\((.+)\)$#', $href, $matches)) {
            $href = trim($matches[1]);
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if ('' === $href || str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return $href;
        }

        if (1 === preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            // Already has a scheme: http(s), mailto, tel, ...
            return $href;
        }

        if (false !== filter_var($href, \FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $href;
        }

        return 'https://' . $href;
    }
}
