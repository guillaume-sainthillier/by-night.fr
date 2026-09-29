<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\ShareLinks;
use PHPUnit\Framework\TestCase;

final class ShareLinksTest extends TestCase
{
    private const string URL = 'https://by-night.fr/rouen/soiree/le-gros-rouen--42';

    public function testFacebookSharesTheUrl(): void
    {
        $links = ShareLinks::forPage(self::URL, 'Le Gros Rouen');

        self::assertStringStartsWith('https://www.facebook.com/sharer/sharer.php?', $links['facebook']);
        self::assertSame(['u' => self::URL, 't' => 'Le Gros Rouen', 'display' => 'popup'], $this->query($links['facebook']));
    }

    public function testXPostsTheTitleAndTheUrl(): void
    {
        $links = ShareLinks::forPage(self::URL, 'Le Gros Rouen');

        self::assertStringStartsWith('https://twitter.com/intent/tweet?', $links['twitter']);
        self::assertSame(['text' => 'Le Gros Rouen', 'url' => self::URL], $this->query($links['twitter']));
    }

    public function testTheTitleIsOneLineOfPlainText(): void
    {
        $links = ShareLinks::forPage(self::URL, "  <b>Rock &amp; Roll</b>\n\n à  l'Olympia ");

        self::assertSame("Rock & Roll à l'Olympia", $this->query($links['twitter'])['text']);
    }

    /**
     * @return array<string, string>
     */
    private function query(string $link): array
    {
        parse_str((string) parse_url($link, \PHP_URL_QUERY), $query);

        /* @var array<string, string> $query */
        return $query;
    }
}
