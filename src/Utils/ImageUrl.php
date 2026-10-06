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
 * Whether two image URLs a source gave name the same picture, so that a new URL alone downloads nothing again.
 */
final class ImageUrl
{
    /**
     * Hosts serving the same files under the same paths. OpenAgenda moved its agendas from cdn. to img. in 2026:
     * img. serves the same picture recompressed (same dimensions, ~40% of the bytes), so taking it again replaced
     * every image with a lighter copy, and purged its thumbnails and the CDN for nothing.
     */
    private const array SAME_FILE_HOSTS = [
        'cdn.openagenda.com' => 'openagenda',
        'img.openagenda.com' => 'openagenda',
    ];

    public static function isSameImage(?string $url, ?string $otherUrl): bool
    {
        if ($url === $otherUrl) {
            return true;
        }

        if (null === $url || null === $otherUrl) {
            return false;
        }

        $key = self::key($url);

        return null !== $key && $key === self::key($otherUrl);
    }

    /**
     * The picture a URL names, when its host shares its files with others: the group of hosts, the path and the query.
     */
    private static function key(string $url): ?string
    {
        $parts = parse_url($url);
        $group = self::SAME_FILE_HOSTS[strtolower($parts['host'] ?? '')] ?? null;
        if (null === $group) {
            return null;
        }

        return $group . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
