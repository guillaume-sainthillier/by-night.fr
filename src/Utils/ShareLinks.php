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
 * The "Partager" links of a page: each network's share dialog, prefilled with the page's URL and title. The keys are
 * the service identifiers the `social_label` / `social_icon` Twig filters know.
 */
final class ShareLinks
{
    /**
     * @return array{facebook: string, twitter: string}
     */
    public static function forPage(string $url, string $title): array
    {
        $title = self::normalize($title);

        return [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?' . http_build_query([
                'u' => $url,
                't' => $title,
                'display' => 'popup',
            ]),
            'twitter' => 'https://twitter.com/intent/tweet?' . http_build_query([
                'text' => $title,
                'url' => $url,
            ]),
        ];
    }

    /**
     * One line of plain text: imported names can carry markup, entities and line breaks.
     */
    private static function normalize(string $text): string
    {
        return trim(strip_tags(html_entity_decode((string) preg_replace('/\s+/u', ' ', $text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
    }
}
