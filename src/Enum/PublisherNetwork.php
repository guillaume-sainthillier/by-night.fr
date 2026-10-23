<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * The social networks a publisher links to from its page and its events (Publisher::$socialLinks), in the order they
 * are shown.
 */
enum PublisherNetwork: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case X = 'x';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::X => 'X',
            self::YouTube => 'YouTube',
            self::LinkedIn => 'LinkedIn',
        };
    }

    public function getIcon(): string
    {
        return 'fa7-brands:' . match ($this) {
            self::Instagram => 'instagram',
            self::Facebook => 'facebook-f',
            self::TikTok => 'tiktok',
            self::X => 'x-twitter',
            self::YouTube => 'youtube',
            self::LinkedIn => 'linkedin-in',
        };
    }

    /**
     * The hosts a link to it may have, subdomains included (www., m., fr-fr.).
     *
     * @return list<string>
     */
    public function getHosts(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com'],
            self::Facebook => ['facebook.com', 'fb.com'],
            self::TikTok => ['tiktok.com'],
            self::X => ['x.com', 'twitter.com'],
            self::YouTube => ['youtube.com', 'youtu.be'],
            self::LinkedIn => ['linkedin.com'],
        };
    }

    /**
     * Whether the URL leads to this network.
     */
    public function owns(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, \PHP_URL_HOST));

        return array_any($this->getHosts(), static fn (string $domain): bool => $host === $domain || str_ends_with($host, '.' . $domain));
    }

    public function getPlaceholder(): string
    {
        return match ($this) {
            self::Instagram => 'https://www.instagram.com/…',
            self::Facebook => 'https://www.facebook.com/…',
            self::TikTok => 'https://www.tiktok.com/@…',
            self::X => 'https://x.com/…',
            self::YouTube => 'https://www.youtube.com/@…',
            self::LinkedIn => 'https://www.linkedin.com/company/…',
        };
    }
}
