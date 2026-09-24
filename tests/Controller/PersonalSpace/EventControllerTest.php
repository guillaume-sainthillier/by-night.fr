<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\EventFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EventControllerTest extends WebTestCase
{
    public function testTheDeleteButtonOfTheActionBarDeletesTheEvent(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $eventId = $event->getId();
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $eventId));

        self::assertResponseIsSuccessful();
        // The button sits in the event form's action bar, its form attribute makes it submit the delete form
        $deleteButton = $crawler->filter('form[name="app_event"] .form-actions-fixed button[form="event-delete-form"]');
        self::assertCount(1, $deleteButton);

        $client->submit($deleteButton->form());

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertSame(0, EventFactory::count(['id' => $eventId]));
    }

    public function testTheDeleteFormOfTheListDeletesTheEvent(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $eventId = $event->getId();
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', '/espace-perso/mes-soirees');

        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter(\sprintf('form.form-delete-%d', $eventId))->form());

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertSame(0, EventFactory::count(['id' => $eventId]));
    }

    public function testADeletionWithoutTheTokenOfItsPageIsRefused(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $eventId = $event->getId();
        $client->loginUser($event->getUser());

        // What a form posted from another site would send
        $client->request('POST', \sprintf('/espace-perso/%d', $eventId), ['_method' => 'DELETE']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(1, EventFactory::count(['id' => $eventId]));
    }
}
