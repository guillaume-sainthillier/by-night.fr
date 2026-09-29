<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Enum\EventStatus;
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

    public function testTheStatusFilterKeepsTheEventsItsSwitchIsSetFor(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        EventFactory::createOne(['user' => $user, 'name' => 'En ligne']);
        EventFactory::createOne(['user' => $user, 'name' => 'Masqué', 'draft' => true]);
        EventFactory::createOne(['user' => $user, 'name' => 'Annulé', 'status' => EventStatus::Cancelled]);
        EventFactory::createOne(['name' => "L'événement d'un autre"]);
        $client->loginUser($user);

        // Newest first; an unknown status (an old link, a hand-edited URL) lists every event
        foreach ([
            '' => ['Annulé', 'Masqué', 'En ligne'],
            'visible' => ['Annulé', 'En ligne'],
            'hidden' => ['Masqué'],
            'cancelled' => ['Annulé'],
            'unknown' => ['Annulé', 'Masqué', 'En ligne'],
        ] as $status => $names) {
            $crawler = $client->request('GET', '/espace-perso/mes-soirees', ['status' => $status]);

            self::assertResponseIsSuccessful();
            self::assertSame($names, $crawler->filter('tbody tr > td:first-child a')->each(static fn ($link) => $link->text()), $status);
        }
    }

    public function testTheStatusFilterCountsTheEventsOfEachStatus(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        EventFactory::createMany(2, ['user' => $user]);
        EventFactory::createOne(['user' => $user, 'draft' => true, 'status' => EventStatus::Cancelled]);
        EventFactory::createOne();
        $client->loginUser($user);

        // Counted whatever the search: the counts tell what each filter would list
        $crawler = $client->request('GET', '/espace-perso/mes-soirees', ['q' => 'no match', 'status' => 'hidden']);

        self::assertResponseIsSuccessful();
        $counters = $crawler->filter('#event-counters a');
        self::assertSame(['Total créés 3', 'En ligne 2', 'Masqués 1', 'Annulés 1'], $counters->each(static fn ($counter) => $counter->text()));
        // Each counter keeps the search; the search form keeps the status
        self::assertSame('/espace-perso/mes-soirees?q=no%20match&status=cancelled', $counters->last()->attr('href'));
        self::assertSame('Masqués 1', $crawler->filter('#event-counters a[aria-current="page"]')->text());
        self::assertSame('hidden', $crawler->filter('#event-filters input[name="status"]')->attr('value'));
        self::assertSelectorTextContains('.empty-title', 'Aucun événement ne correspond à votre recherche');
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

    public function testAStatusMessageIsSavedWithItsStatus(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[status]'] = EventStatus::Postponed->value;
        $form['app_event[statusMessage]'] = 'Reporté au 15 mars';
        $client->submit($form);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        $saved = EventFactory::find(['id' => $event->getId()]);
        self::assertSame(EventStatus::Postponed, $saved->getStatus());
        self::assertSame('Reporté au 15 mars', $saved->getStatusMessage());
    }

    /**
     * The form hides the message while the event is "Programmé", and the event page shows a message saved without a
     * status: one sent all the same (without JavaScript, or forged) is dropped.
     */
    public function testAStatusMessageWithoutStatusIsDropped(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['status' => EventStatus::Postponed, 'statusMessage' => 'Reporté au 15 mars']);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $form = $crawler->filter('form[name="app_event"]')->form();
        $form['app_event[status]'] = '';
        $client->submit($form);

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        $saved = EventFactory::find(['id' => $event->getId()]);
        self::assertNull($saved->getStatus());
        self::assertNull($saved->getStatusMessage());
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
        self::assertFalse($event->isDraft());
    }

    public function testCreatingADraftSavesItOffTheSite(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['verified' => true, 'enabled' => true]);
        CountryFactory::createOne(['id' => 'FR', 'name' => 'France', 'postalCodeRegex' => '^\\d{5}$']);
        $client->loginUser($user);

        $client->submit($this->newEventForm($client, 'Soirée swing en préparation', 'app_event[saveDraft]'));

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertTrue(EventFactory::find(['name' => 'Soirée swing en préparation'])->isDraft());
    }

    public function testSavingAnEventAsDraftTakesItOffTheSite(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['draft' => false]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $client->submit($crawler->selectButton('app_event[saveDraft]')->form());

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertTrue(EventFactory::find(['id' => $event->getId()])->isDraft());
    }

    public function testTheMainButtonPutsADraftOnline(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['draft' => true]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));
        $client->submit($crawler->selectButton('Publier l\'événement')->form());

        self::assertResponseRedirects('/espace-perso/mes-soirees');
        self::assertFalse(EventFactory::find(['id' => $event->getId()])->isDraft());
    }

    /**
     * Enter in a field sends the form with its first submit button: the publish one, written before the draft one
     * although it is shown after it.
     */
    public function testTheFirstSubmitButtonOfTheFormPublishes(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['draft' => true]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d', $event->getId()));

        // The delete button sits in the bar but belongs to the delete form (its form attribute)
        $buttons = $crawler->filter('form[name="app_event"] button[type="submit"]:not([form])');
        self::assertSame('Publier l\'événement', $buttons->first()->text());
        self::assertSame('app_event[saveDraft]', $buttons->eq(1)->attr('name'));
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

    /**
     * @param string|null $button The name of the submit button to send the form with, none by default
     */
    private function newEventForm(KernelBrowser $client, string $name, ?string $button = null): Form
    {
        $crawler = $client->request('GET', '/espace-perso/nouvelle-soiree');
        self::assertResponseIsSuccessful();
        $form = (null === $button ? $crawler->filter('form[name="app_event"]') : $crawler->selectButton($button))->form();
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
