<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Invalidator;

use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Invalidator\TagsInvalidator;
use Exception;
use FOS\HttpCacheBundle\CacheManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TagsInvalidatorTest extends TestCase
{
    private CacheManager $cacheManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testAddCityAddsCorrectTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(42);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-42']);

        $invalidator->addCity($city);
        $invalidator->flush();
    }

    public function testAddUserAddsCorrectTag(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['user-123']);

        $invalidator->addUser($user);
        $invalidator->flush();
    }

    public function testAddEventAddsCorrectTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $location = new Location();
        $location->setCity($city);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(10);
        $place->method('getLocation')->willReturn($location);

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(99);
        $event->method('getPlace')->willReturn($place);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['event-99', 'place-10', 'location-1']);

        $invalidator->addEvent($event);
        $invalidator->flush();
    }

    public function testAddEventWithoutIdDoesNotAddEventTag(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(null);
        $event->method('getPlace')->willReturn(null);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $invalidator->addEvent($event);
        $invalidator->flush();
    }

    public function testAddEventWithoutPlaceOnlyAddsEventTag(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(99);
        $event->method('getPlace')->willReturn(null);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['event-99']);

        $invalidator->addEvent($event);
        $invalidator->flush();
    }

    public function testAddPlaceAddsCorrectTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(2);

        $location = new Location();
        $location->setCity($city);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(20);
        $place->method('getLocation')->willReturn($location);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['place-20', 'location-2']);

        $invalidator->addPlace($place);
        $invalidator->flush();
    }

    public function testAddPlaceWithoutIdOnlyAddsLocationTag(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(3);

        $location = new Location();
        $location->setCity($city);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(null);
        $place->method('getLocation')->willReturn($location);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['location-3']);

        $invalidator->addPlace($place);
        $invalidator->flush();
    }

    public function testAddUserEventAddsCorrectTag(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(55);

        $userEvent = $this->createMock(UserEvent::class);
        $userEvent->method('getEvent')->willReturn($event);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['tendances-55']);

        $invalidator->addUserEvent($userEvent);
        $invalidator->flush();
    }

    public function testFlushWithoutTagsDoesNotInvalidate(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $invalidator->flush();
    }

    public function testFlushBatchesMultipleTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(2);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-1', 'user-2']);

        $invalidator->addCity($city);
        $invalidator->addUser($user);
        $invalidator->flush();
    }

    public function testFlushRemovesDuplicateTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-1']);

        $invalidator->addCity($city);
        $invalidator->addCity($city); // Add same city twice
        $invalidator->flush();
    }

    public function testFlushClearsTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $this->cacheManager->expects($this->exactly(2))
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-1']);

        $invalidator->addCity($city);
        $invalidator->flush();

        // After flush, tags should be cleared
        $invalidator->addCity($city);
        $invalidator->flush();
    }

    public function testFlushHandlesExceptions(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, true);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $exception = new Exception('Cache invalidation failed');
        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Cache invalidation failed', ['exception' => $exception]);

        $invalidator->addCity($city);
        $invalidator->flush();
    }

    public function testHttpCacheDisabledDoesNotAddTags(): void
    {
        $invalidator = new TagsInvalidator($this->cacheManager, $this->logger, false);

        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $invalidator->addCity($city);
        $invalidator->flush();
    }

    public function testStaticMethodsReturnCorrectTags(): void
    {
        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(42);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(99);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(20);

        $locationCity = $this->createMock(City::class);
        $locationCity->method('getId')->willReturn(4);

        $location = new Location();
        $location->setCity($locationCity);

        self::assertEquals('header', TagsInvalidator::getHeaderTag());
        self::assertEquals('autocomplete-city', TagsInvalidator::getAutocompleteCityTag());
        self::assertEquals('city-42', TagsInvalidator::getCityTag($city));
        self::assertEquals('user-123', TagsInvalidator::getUserTag($user));
        self::assertEquals('event-99', TagsInvalidator::getEventTag($event));
        self::assertEquals('tendances-99', TagsInvalidator::getTrendTag($event));
        self::assertEquals('place-20', TagsInvalidator::getPlaceTag($place));
        self::assertEquals('location-4', TagsInvalidator::getLocationTag($location));
    }
}
