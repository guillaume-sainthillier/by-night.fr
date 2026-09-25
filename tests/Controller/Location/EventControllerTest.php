<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Entity\Event;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use IntlDateFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventControllerTest extends WebTestCase
{
    public function testAdminSeesEditButtonNextToTitle(): void
    {
        // createClient() must run before any factory call: factories boot the kernel,
        // and WebTestCase refuses to create a client on an already-booted kernel.
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            '.page-header a[href="/_administration/event/%d/edit"]',
            $event->getId()
        ));
    }

    public function testRegularUserDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
    }

    public function testAnonymousDoesNotSeeEditButton(): void
    {
        $client = self::createClient();
        $event = $this->createEvent();

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.page-header a[href^="/_administration/"]');
        self::assertSelectorTextContains('.page-header h1', $event->getName());
    }

    public function testAnEventThatEndedLongAgoStaysIndexableWithAnEndedNotice(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('-10 years'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorTextContains('#event-ended', 'Cet événement est terminé');
    }

    public function testADraftEventIsNotIndexed(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'), draft: true);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testAnUpcomingEventIsIndexable(): void
    {
        $client = self::createClient();
        $event = $this->createEvent(new DateTimeImmutable('+10 days'));

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSelectorNotExists('#event-ended');
    }

    public function testALongListShowsTheNextSessionsAndFoldsThePastAndLaterOnes(): void
    {
        $client = self::createClient();
        $event = $this->createEventWithSessions([-3, -2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.timesheets > .timesheet-entry');
        self::assertSelectorTextContains('.timesheets > .timesheet-entry', $this->day(1), 'The next session comes first');
        self::assertSelectorCount(2, '.timesheets > details');
        self::assertSelectorTextContains('.timesheets > details:first-child summary', 'Voir les 3 dates passées');
        self::assertSelectorCount(3, '.timesheets > details:first-child .timesheet-entry-past');
        self::assertSelectorTextContains('.timesheets > details:last-child summary', 'Voir les 4 dates suivantes');
        self::assertSelectorCount(12, '.timesheets .timesheet-entry', 'Every date stays in the page');
    }

    public function testAFoldOfASingleDateIsNotWorthALink(): void
    {
        $client = self::createClient();
        // One past session and six to come: five shown, one left over
        $event = $this->createEventWithSessions([-1, 1, 2, 3, 4, 5, 6]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.timesheets details');
        self::assertSelectorCount(7, '.timesheets > .timesheet-entry');
        self::assertSelectorCount(1, '.timesheets > .timesheet-entry-past');
    }

    public function testAnEventThatIsOverListsItsSessionsFromTheStart(): void
    {
        $client = self::createClient();
        $event = $this->createEventWithSessions([-20, -19, -18, -17, -16, -15, -14, -13]);

        $client->request('GET', $this->eventUrl($event));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.timesheets > .timesheet-entry');
        self::assertSelectorTextContains('.timesheets > .timesheet-entry', $this->day(-20));
        self::assertSelectorCount(1, '.timesheets > details');
        self::assertSelectorTextContains('.timesheets > details summary', 'Voir les 3 dates suivantes');
    }

    /**
     * @param list<int> $days sessions, in days from today
     */
    private function createEventWithSessions(array $days): Event
    {
        $city = CityFactory::toulouse()->create();

        return EventFactory::createOne([
            'name' => 'Exposition',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            'startDate' => new DateTimeImmutable(\sprintf('%+d days', min($days))),
            'endDate' => new DateTimeImmutable(\sprintf('%+d days', max($days))),
            'timesheets' => array_map(
                static fn (int $day) => EventTimesheetFactory::new()->on(\sprintf('%+d days', $day)),
                $days,
            ),
        ]);
    }

    /**
     * A session's day as the page writes it, e.g. "samedi 26 septembre 2026".
     */
    private function day(int $offset): string
    {
        return new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE)->format(new DateTimeImmutable(\sprintf('%+d days', $offset)));
    }

    private function createEvent(?DateTimeImmutable $date = null, bool $draft = false): Event
    {
        $city = CityFactory::toulouse()->create();
        $attributes = [
            'name' => 'Concert au Bikini',
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            'draft' => $draft,
        ];
        if (null !== $date) {
            $attributes += ['startDate' => $date, 'endDate' => $date];
        }

        return EventFactory::createOne($attributes);
    }

    private function eventUrl(Event $event): string
    {
        return \sprintf(
            '/%s/soiree/%s--%d',
            $event->getLocationSlug(),
            $event->getSlug(),
            $event->getId()
        );
    }
}
