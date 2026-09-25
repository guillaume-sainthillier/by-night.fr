<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
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

    public function testEditingAnEventSavesIt(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[name]'] = 'Concert de jazz manouche';
        $client->submit($form);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertSame('Concert de jazz manouche', EventFactory::find(['id' => $event->getId()])->getName());
    }

    /**
     * The edit goes through a DTO that EventEntityFactory writes back field by field:
     * what the DTO leaves out is erased, although the form never showed it.
     */
    public function testEditingAnEventKeepsWhatTheFormDoesNotShow(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz', 'type' => 'Concert']);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[name]'] = 'Concert de jazz manouche';
        $client->submit($form);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        $saved = EventFactory::find(['id' => $event->getId()]);
        self::assertSame('Concert', $saved->getType());
    }

    /**
     * The event used to be saved while the form was being submitted, before its validation:
     * a forged cross-site submission, without the token, edited the event all the same.
     */
    public function testAnEditWithoutTheTokenOfItsPageSavesNothing(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[name]'] = 'Forged name';
        $form['app_event[_token]'] = 'forged';
        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Concert de jazz', EventFactory::find(['id' => $event->getId()])->getName());
    }

    public function testAnEditTheFirewallRefusesSavesNothing(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[name]'] = 'Film Streaming gratuit';
        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Concert de jazz', EventFactory::find(['id' => $event->getId()])->getName());
    }

    public function testCreatingAnEventSavesIt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        CountryFactory::createOne(['id' => 'FR', 'name' => 'France', 'postalCodeRegex' => '^\\d{5}$']);
        $client->loginUser($user);

        $client->submit($this->newEventForm($client, 'Soirée swing au Bikini'));

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        $event = EventFactory::find(['name' => 'Soirée swing au Bikini']);
        self::assertSame($user->getId(), $event->getUser()?->getId());
    }

    public function testACreationWithoutTheTokenOfItsPageSavesNothing(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        CountryFactory::createOne(['id' => 'FR', 'name' => 'France', 'postalCodeRegex' => '^\\d{5}$']);
        $client->loginUser($user);

        $form = $this->newEventForm($client, 'Soirée forgée');
        $form['app_event[_token]'] = 'forged';
        $client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, EventFactory::count(['name' => 'Soirée forgée']));
    }

    private function newEventForm(KernelBrowser $client, string $name): Form
    {
        $crawler = $client->request('GET', '/espace-perso/nouvelle-soiree');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[name]'] = $name;
        $form['app_event[description]'] = 'Une grande soirée de danse swing, avec initiation pour les débutants.';
        $form['app_event[dateRange][from]'] = new DateTimeImmutable('+1 week')->format('Y-m-d');
        $form['app_event[dateRange][to]'] = new DateTimeImmutable('+1 week')->format('Y-m-d');
        $form['app_event[place][name]'] = 'Le Bikini';
        $form['app_event[place][street]'] = 'Rue Théodore Monod';
        $form['app_event[place][city][name]'] = 'Ramonville-Saint-Agne';
        $form['app_event[place][city][postalCode]'] = '31520';
        $form['app_event[place][country]'] = 'FR';

        return $form;
    }
}
