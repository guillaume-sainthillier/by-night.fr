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
use App\Factory\EventVisitStatFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

final class EventStatsTest extends AppWebTestCase
{
    public function testTheAuthorSeesTheVisitorsAndTicketingClicksOfTheLast30Days(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('today'), 'visitors' => 12, 'ticketClicks' => 3]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-10 days'), 'visitors' => 28, 'ticketClicks' => 1]);
        // Before the 30 days: only in the total
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-60 days'), 'visitors' => 100, 'ticketClicks' => 9]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Concert de jazz');
        self::assertSame(['40', '4', "10,0\u{a0}%", '140'], $this->tiles($crawler));
        self::assertSelectorTextSame('#event-stats-periods .active', '30 derniers jours');
        self::assertCount(30, $crawler->filter('#event-stats-days .card-table tbody tr'));
        self::assertSelectorExists('#event-stats-chart path[data-series="visitors"]');
        self::assertSelectorExists('#event-stats-chart path[data-series="ticketClicks"]');
        self::assertCount(30, $crawler->filter('#event-stats-chart rect title'));
    }

    public function testAShortcutShowsItsDays(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('today'), 'visitors' => 12, 'ticketClicks' => 3]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-10 days'), 'visitors' => 28, 'ticketClicks' => 1]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques?period=last_7_days', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSame('12', $this->tiles($crawler)[0]);
        self::assertSelectorTextSame('#event-stats-periods .active', '7 derniers jours');
        self::assertCount(7, $crawler->filter('#event-stats-days .card-table tbody tr'));
    }

    public function testTheDatesPickedWinOverTheShortcut(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $from = new DateTimeImmutable('-12 days');
        $to = new DateTimeImmutable('-8 days');
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-10 days'), 'visitors' => 28, 'ticketClicks' => 1]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('today'), 'visitors' => 12, 'ticketClicks' => 3]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())), [
            'period' => 'last_7_days',
            'dateRange' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('28', $this->tiles($crawler)[0]);
        self::assertSelectorNotExists('#event-stats-periods .active');
        self::assertCount(5, $crawler->filter('#event-stats-days .card-table tbody tr'));
        self::assertSelectorTextContains('#event-stats .card-title', 'Du ');
    }

    public function testAnUnknownShortcutShowsTheLast30Days(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser($event->getUser());

        $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques?period=forever', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#event-stats-periods .active', '30 derniers jours');
    }

    public function testAnEventWithoutVisitsSaysSo(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser($event->getUser());

        $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#event-stats .empty-title', 'Pas de visite sur cette période');
        self::assertSelectorNotExists('#event-stats-chart');
        self::assertSelectorNotExists('#event-stats details');
        self::assertSelectorNotExists('#event-stats-google-analytics');
    }

    public function testTheDaysReadFromGoogleAnalyticsAreSaidToCountFewerVisitors(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-3 days'), 'visitors' => 4, 'ticketClicks' => 0, 'fromGoogleAnalytics' => true]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-2 days'), 'visitors' => 10, 'ticketClicks' => 1]);
        $client->loginUser($event->getUser());

        $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#event-stats-google-analytics', 'Google Analytics');
    }

    public function testTheNoteIsLeftOutOfAPeriodWithoutGoogleAnalyticsDays(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-60 days'), 'visitors' => 4, 'ticketClicks' => 0, 'fromGoogleAnalytics' => true]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-2 days'), 'visitors' => 10, 'ticketClicks' => 1]);
        $client->loginUser($event->getUser());

        $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#event-stats-google-analytics');
    }

    public function testAnotherMemberCannotSeeThem(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testADuplicateShowsTheAudienceOfThePageItRedirectsTo(): void
    {
        $client = self::createClient();
        $canonical = EventFactory::createOne();
        $event = EventFactory::createOne(['duplicateOf' => $canonical]);
        EventVisitStatFactory::createOne(['event' => $canonical, 'day' => new DateTimeImmutable('today'), 'visitors' => 7, 'ticketClicks' => 2]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', self::organizerPath($event->getUser(), \sprintf('evenements/%d/statistiques', $event->getId())));

        self::assertResponseIsSuccessful();
        self::assertSame('7', $this->tiles($crawler)[0]);
    }

    public function testTheListShowsTheAudienceOfTheLast30Days(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user]);
        $quiet = EventFactory::createOne(['user' => $user]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('yesterday'), 'visitors' => 21, 'ticketClicks' => 1]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-45 days'), 'visitors' => 500, 'ticketClicks' => 50]);
        $client->loginUser($user);

        $crawler = $client->request('GET', self::organizerPath($user));

        self::assertResponseIsSuccessful();
        $cell = $crawler->filter(\sprintf('a[href="%s"].text-reset', self::organizerPath($user, \sprintf('evenements/%d/statistiques', $event->getId()))));
        self::assertSame('21 visiteurs 1 clic billetterie', $cell->text());
        self::assertSame('0 visiteur', $crawler->filter(\sprintf('a[href="%s"].text-reset', self::organizerPath($user, \sprintf('evenements/%d/statistiques', $quiet->getId()))))->text());
    }

    /**
     * @return list<string>
     */
    private function tiles(Crawler $crawler): array
    {
        return $crawler->filter('#event-stats .row-cards .h1')->each(static fn (Crawler $node): string => $node->text());
    }
}
