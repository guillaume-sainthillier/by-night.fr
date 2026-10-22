<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\EventFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

/**
 * The back office saves a calendar without EventParticipationManager: the counters of its event follow all the same.
 */
final class UserEventCrudControllerTest extends AppWebTestCase
{
    public function testEditingACalendarRecountsItsEvent(): void
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        $userEvent = UserEventFactory::createOne(['going' => true, 'wish' => false]);
        $eventId = $userEvent->getEvent()->getId();

        $crawler = $client->request('GET', \sprintf('/_administration/user-event/%d/edit', $userEvent->getId()));
        $form = $crawler->selectButton('Sauvegarder les modifications')->form();
        $client->submit($form, ['UserEvent[going]' => false, 'UserEvent[wish]' => true]);

        self::assertResponseRedirects();
        $event = EventFactory::find(['id' => $eventId]);
        self::assertSame(0, $event->getParticipations());
        self::assertSame(1, $event->getInterests());
    }
}
