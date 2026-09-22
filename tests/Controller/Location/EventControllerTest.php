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
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventControllerTest extends WebTestCase
{
    public function testAdminSeesEditButtonNextToTitle(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            '.page-header a[href="/_administration/event/%d/edit"]',
            $event->getId()
        ));
    }

    public function testRegularUserDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
    }

    public function testAnonymousDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
        self::assertSelectorTextContains('.page-header h1', $event->getName());
    }

    public function testAnEventThatEndedLongAgoStaysOnlineButIsNotIndexed(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('-60 days'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertSelectorTextContains('#event-ended', 'Cet événement est terminé');
    }

    public function testAnUpcomingEventIsIndexable(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorNotExists('#event-ended');
    }

    private function createEvent(?DateTimeImmutable $date = null): Event
    {
        $city = CityFactory::toulouse()->create();
        $attributes = [
            'name' => 'Concert au Bikini',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
        ];
        if (null !== $date) {
            $attributes += ['startDate' => $date, 'endDate' => $date];
        }

        return EventFactory::createOne($attributes);
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
