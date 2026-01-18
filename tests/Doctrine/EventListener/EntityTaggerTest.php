<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\App\Location;
use App\Doctrine\EventListener\EntityTagger;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Invalidator\TagsInvalidator;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use FOS\HttpCacheBundle\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

final class EntityTaggerTest extends TestCase
{
    private TagsInvalidator $invalidator;

    /** @var CacheManager&MockObject */
    private CacheManager $cacheManager;

    private EntityTagger $tagger;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManager::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->invalidator = new TagsInvalidator($this->cacheManager, $logger, true);
        $this->tagger = new EntityTagger($this->invalidator);
    }

    public function testPostFlushCallsInvalidatorFlush(): void
    {
        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->postFlush();
    }

    public function testPostPersistTagsCity(): void
    {
        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($city);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-1']);

        $this->tagger->postPersist($args);
        $this->tagger->postFlush();
    }

    public function testPostPersistTagsEventPlace(): void
    {
        $cityMock = $this->createMock(City::class);
        $cityMock->method('getId')->willReturn(1);

        $location = new Location();
        $location->setCity($cityMock);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(10);
        $place->method('getLocation')->willReturn($location);

        $event = $this->createMock(Event::class);
        $event->method('getPlace')->willReturn($place);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['place-10', 'location-1']);

        $this->tagger->postPersist($args);
        $this->tagger->postFlush();
    }

    public function testPostPersistIgnoresEventWithoutPlace(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getPlace')->willReturn(null);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->postPersist($args);
        $this->tagger->postFlush();
    }

    public function testPostPersistIgnoresUnknownEntities(): void
    {
        $unknownEntity = new stdClass();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($unknownEntity);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->postPersist($args);
        $this->tagger->postFlush();
    }

    public function testPreUpdateTagsEventPlace(): void
    {
        $cityMock = $this->createMock(City::class);
        $cityMock->method('getId')->willReturn(1);

        $location = new Location();
        $location->setCity($cityMock);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(10);
        $place->method('getLocation')->willReturn($location);

        $event = $this->createMock(Event::class);
        $event->method('getPlace')->willReturn($place);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['place-10', 'location-1']);

        $this->tagger->preUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPreUpdateIgnoresEventWithoutPlace(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getPlace')->willReturn(null);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->preUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPreUpdateIgnoresNonEventEntities(): void
    {
        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(1);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($city);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->preUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateTagsUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($user);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['user-123']);

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateTagsCity(): void
    {
        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(42);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($city);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-42']);

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateTagsEvent(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(99);
        $event->method('getPlace')->willReturn(null);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['event-99']);

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateTagsPlace(): void
    {
        $cityMock = $this->createMock(City::class);
        $cityMock->method('getId')->willReturn(1);

        $location = new Location();
        $location->setCity($cityMock);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(20);
        $place->method('getLocation')->willReturn($location);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($place);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['place-20', 'location-1']);

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateTagsUserEvent(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(55);

        $userEvent = $this->createMock(UserEvent::class);
        $userEvent->method('getEvent')->willReturn($event);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($userEvent);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['tendances-55']);

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPostUpdateIgnoresUnknownEntities(): void
    {
        $unknownEntity = new stdClass();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($unknownEntity);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->postUpdate($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveTagsUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(123);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($user);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['user-123']);

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveTagsCity(): void
    {
        $city = $this->createMock(City::class);
        $city->method('getId')->willReturn(42);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($city);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['autocomplete-city', 'city-42']);

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveTagsEvent(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(99);
        $event->method('getPlace')->willReturn(null);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($event);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['event-99']);

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveTagsPlace(): void
    {
        $cityMock = $this->createMock(City::class);
        $cityMock->method('getId')->willReturn(1);

        $location = new Location();
        $location->setCity($cityMock);

        $place = $this->createMock(Place::class);
        $place->method('getId')->willReturn(20);
        $place->method('getLocation')->willReturn($location);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($place);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['place-20', 'location-1']);

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveTagsUserEvent(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(55);

        $userEvent = $this->createMock(UserEvent::class);
        $userEvent->method('getEvent')->willReturn($event);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($userEvent);

        $this->cacheManager->expects($this->once())
            ->method('invalidateTags')
            ->with(['tendances-55']);

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }

    public function testPreRemoveIgnoresUnknownEntities(): void
    {
        $unknownEntity = new stdClass();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($unknownEntity);

        $this->cacheManager->expects($this->never())
            ->method('invalidateTags');

        $this->tagger->preRemove($args);
        $this->tagger->postFlush();
    }
}
