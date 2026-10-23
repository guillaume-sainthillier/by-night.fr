<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Entity\Publisher;
use App\Enum\PublisherNetwork;
use PHPUnit\Framework\TestCase;

final class PublisherTest extends TestCase
{
    public function testTheEmptyLinksAndTheUnknownNetworksAreDropped(): void
    {
        $publisher = new Publisher()->setSocialLinks(['instagram' => ' https://instagram.com/lebikini ', 'facebook' => '', 'tiktok' => null, 'myspace' => 'https://myspace.com/lebikini']);

        self::assertSame(['instagram' => 'https://instagram.com/lebikini'], $publisher->getSocialLinks());
    }

    public function testTheLinksAreListedInTheOrderOfTheNetworks(): void
    {
        $publisher = new Publisher()->setSocialLinks(['youtube' => 'https://youtube.com/@lebikini', 'instagram' => 'https://instagram.com/lebikini']);

        self::assertSame([PublisherNetwork::Instagram, PublisherNetwork::YouTube], array_column($publisher->getNetworkLinks(), 'network'));
    }

    public function testABlankDescriptionIsNone(): void
    {
        self::assertNull(new Publisher()->setDescription("  \n ")->getDescription());
    }
}
