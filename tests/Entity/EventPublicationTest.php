<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Entity;

use App\Factory\EventFactory;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Event::$publishedAt, stamped by Doctrine when an event is first saved online (Event::stampPublication()).
 */
final class EventPublicationTest extends AppKernelTestCase
{
    public function testAnEventCreatedOnlineIsPublished(): void
    {
        $event = EventFactory::createOne();

        refresh($event);

        self::assertNotNull($event->getPublishedAt());
    }

    public function testADraftPutOnlineIsPublishedThenAndKeepsItsDateWhenPutOnlineAgain(): void
    {
        $event = EventFactory::createOne(['draft' => true]);
        self::assertNull($event->getPublishedAt());

        save($event->setDraft(false));
        refresh($event);
        $published = $event->getPublishedAt();
        self::assertNotNull($published, 'Written by the update that put it online');

        save($event->setDraft(true));
        save($event->setDraft(false));
        refresh($event);
        self::assertEquals($published, $event->getPublishedAt());
    }
}
