<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\EventListener;

use App\Factory\EventFactory;
use App\Picture\EventCard;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\delete;

final class EventCardRemovalListenerTest extends AppKernelTestCase
{
    public function testDeletingAnEventDeletesItsCard(): void
    {
        $event = EventFactory::createOne();
        $storage = self::getContainer()->get('og.storage');
        $location = self::getContainer()->get(EventCard::class)->location($event);
        self::assertTrue($storage->fileExists($location));

        delete($event);

        self::assertFalse($storage->fileExists($location));
    }
}
