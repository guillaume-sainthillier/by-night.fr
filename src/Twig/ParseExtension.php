<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension as Extension;
use Twig\TwigFilter;

class ParseExtension extends Extension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('parse_tags', $this->parseTags(...)),
            new TwigFilter('ensure_protocol', $this->ensureProtocol(...)),
            new TwigFilter('resume', $this->resume(...)),
        ];
    }

    public function ensureProtocol(?string $link): ?string
    {
        if (!preg_match('#^(http|https|ftp)#', $link)) {
            return 'http://' . $link;
        }

        return $link;
    }

    public function parseTags(?string $texte): ?string
    {
        $texte = preg_replace("#<a(.*)href=['\"]([^'^\"]*)['\"]([^>]*)>#", '<a href="$2" target="_blank" rel="nofollow">', $texte);
        $texte = preg_replace("#(^|[\n ])((http|https|ftp)://)?([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1<a href="\\4" target="_blank" rel="nofollow">\\4</a>', $texte);

        if (!preg_match('#<(.*)(script|style|link)#i', $texte)) {
            return $texte;
        }

        return strip_tags($texte, '<a><abbr><acronym><address><article><aside><b><bdo><big><blockquote><br><caption><cite><code><col><colgroup><dd><del><details><dfn><div><dl><dt><em><figcaption><figure><font><h1><h2><h3><h4><h5><h6><hgroup><hr><i><img><ins><li><map><mark><menu><meter><ol><p><pre><q><rp><rt><ruby><s><samp><section><small><span><strong><style><sub><summary><sup><table><tbody><td><tfoot><th><thead><time><tr><tt><u><ul><var><wbr>');
    }

    public function resume(?string $text): string
    {
        if (null === $text) {
            return '';
        }

        $replaced_text = str_replace('&#13;', '<br>', $text);
        $stripped_text = strip_tags($replaced_text);
        $shorted_text = mb_substr($stripped_text, 0, 250);

        // striptags[:250]|replace({'&#13;': '<br>'})|trim|raw|trim('<br><br />')|raw
        $linked_text = preg_replace_callback('
            \#((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|"|\'|:|\<|$|\.\s)\#i',
            static fn ($matches) => '<a rel="nofollow" href="$1" target="_blank">$3</a>$4',
            $shorted_text
        );

        $final_text = $this->trimBr($linked_text);

        return trim($final_text);
    }

    private function trimBr(?string $string): ?string
    {
        $string = preg_replace('#^\s*(?:<br\s*\/?>\s*)*#i', '', $string);

        return preg_replace('#\s*(?:<br\s*\/?>\s*)*$#i', '', $string);
    }
}
