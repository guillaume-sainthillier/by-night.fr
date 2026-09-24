<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\RegisterListenersService;
use Doctrine\Persistence\ObjectManager;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PostPersistEvent;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Provider\PagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RegisterListenersServiceTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private RegisterListenersService $service;

    private ObjectPersisterInterface $objectPersister;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->service = new RegisterListenersService($this->dispatcher);
        $this->objectPersister = $this->createStub(ObjectPersisterInterface::class);
    }

    public function testTheManagerIsClearedAfterEachPageOfItsOwnPager(): void
    {
        $pager = $this->createStub(PagerInterface::class);
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::exactly(2))->method('clear');

        $this->service->register($manager, $pager, []);

        $this->dispatcher->dispatch(new PostInsertObjectsEvent($pager, $this->objectPersister, [], []));
        $this->dispatcher->dispatch(new PostInsertObjectsEvent($pager, $this->objectPersister, [], []));
        // Another index being populated in the same process
        $this->dispatcher->dispatch(new PostInsertObjectsEvent($this->createStub(PagerInterface::class), $this->objectPersister, [], []));
    }

    public function testTheListenersAreRemovedOnceThePagerIsPersisted(): void
    {
        // One pager per AsyncPersistPage message handled by the same worker
        foreach (range(1, 3) as $message) {
            $pager = $this->createStub(PagerInterface::class);
            $this->service->register($this->createStub(ObjectManager::class), $pager, []);
            $this->dispatcher->dispatch(new PostPersistEvent($pager, $this->objectPersister, []));
        }

        self::assertSame([], $this->dispatcher->getListeners(PostInsertObjectsEvent::class));
        self::assertSame([], $this->dispatcher->getListeners(PostPersistEvent::class));
    }

    public function testTheListenersOfAPagerStillInProgressAreKept(): void
    {
        $inProgress = $this->createStub(PagerInterface::class);
        $done = $this->createStub(PagerInterface::class);
        $this->service->register($this->createStub(ObjectManager::class), $inProgress, []);
        $this->service->register($this->createStub(ObjectManager::class), $done, []);

        $this->dispatcher->dispatch(new PostPersistEvent($done, $this->objectPersister, []));

        self::assertCount(1, $this->dispatcher->getListeners(PostInsertObjectsEvent::class));
        self::assertCount(1, $this->dispatcher->getListeners(PostPersistEvent::class));
    }

    public function testNothingIsRegisteredWhenThereIsNothingToDoAfterAPage(): void
    {
        $this->service->register($this->createStub(ObjectManager::class), $this->createStub(PagerInterface::class), [
            'clear_object_manager' => false,
        ]);

        self::assertSame([], $this->dispatcher->getListeners());
    }
}
