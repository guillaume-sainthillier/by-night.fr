<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Entity\Event;
use App\Exception\RedirectException;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Manager\EventRedirectManager;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An event has one URL: /{city}/soiree/{slug}--{id}. Any other way to reach it is sent there
 * with a redirect, so that search engines keep a single page per event.
 */
final class EventRedirectManagerTest extends AppKernelTestCase
{
    private EventRedirectManager $manager;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get(RequestStack::class)->push(Request::create('/'));
        $this->manager = self::getContainer()->get(EventRedirectManager::class);
    }

    public function testTheCanonicalUrlGivesTheEvent(): void
    {
        $event = $this->createEvent('Concert de jazz');

        self::assertSame($event, $this->manager->getEvent($event->getId(), 'concert-de-jazz', 'toulouse', 'app_event_details'));
    }

    public function testAWrongSlugRedirectsToTheCanonicalUrl(): void
    {
        $event = $this->createEvent('Concert de jazz');

        self::assertSame(
            \sprintf('/toulouse/soiree/concert-de-jazz--%d', $event->getId()),
            $this->redirectOf(fn () => $this->manager->getEvent($event->getId(), 'old-name', 'toulouse', 'app_event_details')),
        );
    }

    public function testAWrongCityRedirectsToTheCanonicalUrl(): void
    {
        $event = $this->createEvent('Concert de jazz');

        self::assertSame(
            \sprintf('/toulouse/soiree/concert-de-jazz--%d', $event->getId()),
            $this->redirectOf(fn () => $this->manager->getEvent($event->getId(), 'concert-de-jazz', 'paris', 'app_event_details')),
        );
    }

    public function testTheOldUrlWithoutIdRedirectsToTheCanonicalUrl(): void
    {
        $event = $this->createEvent('Concert de jazz');

        self::assertSame(
            \sprintf('/toulouse/soiree/concert-de-jazz--%d', $event->getId()),
            $this->redirectOf(fn () => $this->manager->getEvent(null, 'concert-de-jazz', 'toulouse', 'app_event_details')),
        );
    }

    public function testADuplicateRedirectsToItsCanonicalEvent(): void
    {
        $canonical = $this->createEvent('Concert de jazz');
        $duplicate = $this->createEvent('Concert jazz', ['duplicateOf' => $canonical]);

        self::assertSame(
            \sprintf('/toulouse/soiree/concert-de-jazz--%d', $canonical->getId()),
            $this->redirectOf(fn () => $this->manager->getEvent($duplicate->getId(), 'concert-jazz', 'toulouse', 'app_event_details')),
        );
    }

    public function testASubRequestIsNotRedirected(): void
    {
        $event = $this->createEvent('Concert de jazz');
        self::getContainer()->get(RequestStack::class)->push(Request::create('/_fragment'));

        self::assertSame($event, $this->manager->getEvent($event->getId(), 'old-name', 'paris', 'app_event_details'));
    }

    public function testAnUnknownEventIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->manager->getEvent(999_999_999, 'nothing', 'toulouse', 'app_event_details');
    }

    public function testAnUnknownSlugIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->manager->getEvent(null, 'no-event-has-this-slug', 'toulouse', 'app_event_details');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(string $name, array $attributes = []): Event
    {
        $city = CityFactory::findBy(['slug' => 'toulouse'])[0] ?? CityFactory::createOne(['name' => 'Toulouse', 'slug' => 'toulouse']);

        return EventFactory::createOne([
            'name' => $name,
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            ...$attributes,
        ]);
    }

    private function redirectOf(callable $call): string
    {
        try {
            $call();
        } catch (RedirectException $exception) {
            return $exception->getUrl();
        }

        self::fail('No redirect');
    }
}
