<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\Event;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EventControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAdminSeesEditButtonNextToTitle(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = static::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::new()->admin()->create()->_real());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            '.page-header a[href="/_administration/event/%d/edit"]',
            $event->getId()
        ));
    }

    public function testRegularUserDoesNotSeeEditButton(): void
    {
        $client = static::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
    }

    public function testAnonymousDoesNotSeeEditButton(): void
    {
        $client = static::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
        self::assertSelectorTextContains('.page-header h1', $event->getName());
    }

    private function createEvent(): Event
    {
        $city = CityFactory::toulouse()->create();

        return EventFactory::createOne([
            'name' => 'Concert au Bikini',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
        ])->_real();
    }

    private function eventUrl(Event $event): string
    {
        return \sprintf(
            '/%s/soiree/%s--%d',
            $event->getLocationSlug(),
            $event->getSlug(),
            $event->getId()
        );
    }
}
