<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Enum\PublisherNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublisherNetworkTest extends TestCase
{
    /**
     * @return iterable<string, array{PublisherNetwork, string, bool}>
     */
    public static function provideLinks(): iterable
    {
        yield 'its host' => [PublisherNetwork::Instagram, 'https://instagram.com/lebikini', true];
        yield 'a subdomain' => [PublisherNetwork::Facebook, 'https://fr-fr.facebook.com/lebikini', true];
        yield 'its short host' => [PublisherNetwork::YouTube, 'https://youtu.be/xyz', true];
        yield 'its former host' => [PublisherNetwork::X, 'https://twitter.com/lebikini', true];
        yield 'the case of the host' => [PublisherNetwork::TikTok, 'https://WWW.TikTok.com/@lebikini', true];
        yield 'another network' => [PublisherNetwork::Instagram, 'https://www.facebook.com/lebikini', false];
        yield 'a lookalike host' => [PublisherNetwork::Facebook, 'https://notfacebook.com/lebikini', false];
        yield 'the network in the path' => [PublisherNetwork::LinkedIn, 'https://evil.example/linkedin.com', false];
    }

    #[DataProvider('provideLinks')]
    public function testALinkBelongsToTheNetworkOfItsHost(PublisherNetwork $network, string $url, bool $owned): void
    {
        self::assertSame($owned, $network->owns($url));
    }
}
