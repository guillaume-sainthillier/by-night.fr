<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Runs before the URL check of the sanitizer on the src of images and frames: protocol-relative
 * sources ("//www.youtube.com/embed/…", the way the WYSIWYG editor inserts videos) become https
 * instead of being dropped, and frames only keep a source from a known video, audio or map player.
 */
final class MediaSourceSanitizer implements AttributeSanitizerInterface
{
    private const array EMBED_HOSTS = [
        'youtube.com',
        'youtube-nocookie.com',
        'player.vimeo.com',
        'dailymotion.com',
        'w.soundcloud.com',
        'v.calameo.com',
        'google.com',
        'facebook.com',
    ];

    public function getSupportedElements(): array
    {
        return ['img', 'iframe'];
    }

    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        }

        if ('iframe' !== $element) {
            return $value;
        }

        // The players all serve https: an embed copied with http would be blocked as mixed content
        $value = (string) preg_replace('~^http://~i', 'https://', $value);

        return $this->isEmbedSource($value) ? $value : null;
    }

    private function isEmbedSource(string $url): bool
    {
        if ('https' !== strtolower((string) parse_url($url, \PHP_URL_SCHEME))) {
            return false;
        }

        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));

        return array_any(self::EMBED_HOSTS, static fn (string $embedHost): bool => $host === $embedHost || str_ends_with($host, '.' . $embedHost));
    }
}
