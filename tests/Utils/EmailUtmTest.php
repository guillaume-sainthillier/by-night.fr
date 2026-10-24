<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\EmailUtm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailUtmTest extends TestCase
{
    public function testACampaignIsAnEmail(): void
    {
        self::assertSame(
            ['utm_source' => 'weekend-digest', 'utm_medium' => 'email', 'utm_campaign' => '2026-W43'],
            EmailUtm::campaign('weekend-digest', '2026-W43'),
        );
    }

    /**
     * @param array<string, string> $utm
     */
    #[DataProvider('provideUrls')]
    public function testTag(string $url, array $utm, string $expected): void
    {
        self::assertSame($expected, EmailUtm::tag($url, $utm));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function provideUrls(): iterable
    {
        $utm = EmailUtm::campaign('outing-alert', 'cancelled');

        yield 'no query' => ['https://by-night.test/toulouse', $utm, 'https://by-night.test/toulouse?utm_source=outing-alert&utm_medium=email&utm_campaign=cancelled'];
        yield 'after the query' => ['https://by-night.test/toulouse/agenda?page=2', $utm, 'https://by-night.test/toulouse/agenda?page=2&utm_source=outing-alert&utm_medium=email&utm_campaign=cancelled'];
        yield 'before the fragment' => ['https://by-night.test/soiree/concert--1#participer', $utm, 'https://by-night.test/soiree/concert--1?utm_source=outing-alert&utm_medium=email&utm_campaign=cancelled#participer'];
        yield 'values encoded' => ['https://by-night.test', ['utm_content' => 'a b&c'], 'https://by-night.test?utm_content=a%20b%26c'];
        yield 'no parameters' => ['https://by-night.test/toulouse', [], 'https://by-night.test/toulouse'];
    }
}
