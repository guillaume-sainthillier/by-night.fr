<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContentControllerTest extends WebTestCase
{
    public function testTheLegalNoticeNamesTheHostAndTheContact(): void
    {
        $client = self::createClient();

        $client->request('GET', '/mentions-legales');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions légales');
        // The LCEN requires the host's name, address and phone number, and a way to reach the publisher
        self::assertAnySelectorTextContains('p', 'OVH SAS');
        self::assertAnySelectorTextContains('p', '2 rue Kellermann, 59100 Roubaix');
        self::assertSelectorExists('a[href="mailto:support@by-night.fr"]');
        self::assertSelectorExists('a[href="/cookie"]');
    }
}
