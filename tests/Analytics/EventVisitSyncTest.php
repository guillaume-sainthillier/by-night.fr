<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Analytics;

use App\Analytics\EventVisitSync;
use App\Analytics\UmamiClient;
use App\Factory\EventFactory;
use App\Factory\EventVisitStatFactory;
use App\Repository\EventVisitStatRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EventVisitSyncTest extends AppKernelTestCase
{
    private const string WEBSITE_ID = '0b5c1a5e-1111-4222-8333-444455556666';

    /** @var list<array{method: string, url: string}> */
    private array $requests = [];

    public function testCopiesTheVisitorsAndTicketingClicksOfEachEventPage(): void
    {
        $event = EventFactory::createOne();
        $other = EventFactory::createOne();
        $id = $event->getId();
        $otherId = $other->getId();

        $written = $this->sync(
            [[
                ['x' => "/toulouse/soiree/concert--$id", 'y' => 12],
                // Renamed during the day: both paths are the same event's
                ['x' => "/toulouse/soiree/grand-concert--$id", 'y' => 3],
                ['x' => "/paris/soiree/expo--$otherId", 'y' => 5],
                ['x' => '/toulouse/soiree/sortir/concert', 'y' => 40],
                // An event deleted since
                ['x' => '/toulouse/soiree/gone--999999999', 'y' => 7],
            ]],
            [
                ['eventName' => 'ticket-click', 'urlPath' => "/toulouse/soiree/concert--$id"],
                ['eventName' => 'ticket-click', 'urlPath' => "/toulouse/soiree/grand-concert--$id"],
            ],
        );

        self::assertSame(['events' => 2, 'visitors' => 27, 'ticketClicks' => 2], $written);
        $totals = $this->repository()->findTotalsByEvents([$id, $otherId], new DateTimeImmutable('2026-10-01'));
        self::assertSame(['visitors' => 15, 'ticketClicks' => 2], $totals[$id]);
        self::assertSame(['visitors' => 5, 'ticketClicks' => 0], $totals[$otherId]);
    }

    public function testAsksUmamiForTheEventPagesOfTheDayOnly(): void
    {
        $this->sync([], []);

        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('https://umami.test/api/auth/login', $this->requests[0]['url']);
        parse_str((string) parse_url($this->requests[1]['url'], \PHP_URL_QUERY), $query);
        self::assertStringStartsWith('https://umami.test/api/websites/' . self::WEBSITE_ID . '/metrics?', $this->requests[1]['url']);
        self::assertSame('path', $query['type']);
        self::assertSame('c./soiree/', $query['path']);
        self::assertSame((string) new DateTimeImmutable('2026-10-20 00:00:00')->format('Uv'), $query['startAt']);
        self::assertSame((string) new DateTimeImmutable('2026-10-20 23:59:59.999')->format('Uv'), $query['endAt']);
        parse_str((string) parse_url($this->requests[2]['url'], \PHP_URL_QUERY), $query);
        self::assertSame('ticket-click', $query['event']);
        self::assertCount(3, $this->requests, 'One login for both reads');
    }

    public function testReadsEveryPageOfTheMetrics(): void
    {
        $event = EventFactory::createOne();
        $rows = array_map(static fn (int $i): array => ['x' => "/toulouse/soiree/other-$i", 'y' => 1], range(1, 4999));
        $rows[] = ['x' => '/toulouse/soiree/concert--' . $event->getId(), 'y' => 1];

        $this->sync([$rows, [['x' => '/toulouse/soiree/concert--' . $event->getId(), 'y' => 2]]], []);

        self::assertSame(['visitors' => 3, 'ticketClicks' => 0], $this->repository()->findTotalsByEvents([$event->getId()], new DateTimeImmutable('2026-10-01'))[$event->getId()]);
    }

    public function testADayReadAgainReplacesWhatWasReadBefore(): void
    {
        $event = EventFactory::createOne();
        $stale = EventFactory::createOne();
        EventVisitStatFactory::createOne(['event' => $stale, 'day' => new DateTimeImmutable('2026-10-20'), 'visitors' => 40, 'ticketClicks' => 4]);
        EventVisitStatFactory::createOne(['event' => $stale, 'day' => new DateTimeImmutable('2026-10-19'), 'visitors' => 8, 'ticketClicks' => 1]);

        $this->sync([[['x' => '/toulouse/soiree/concert--' . $event->getId(), 'y' => 6]]], []);

        self::assertSame(1, EventVisitStatFactory::count(['event' => $event]));
        self::assertSame(['visitors' => 8, 'ticketClicks' => 1], $this->repository()->findTotalsByEvents([$stale->getId()], new DateTimeImmutable('2026-10-01'))[$stale->getId()], 'Only the day read is replaced');
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function providePaths(): iterable
    {
        yield 'a city' => ['/toulouse/soiree/concert-de-jazz--123', 123];
        yield 'a city under its country' => ['/suisse/geneve/soiree/festival--45', 45];
        yield 'a slug with a double dash' => ['/paris/soiree/a--b--678', 678];
        yield 'a former URL without id' => ['/soiree/concert-de-jazz', null];
        yield 'an agenda' => ['/toulouse/agenda/sortir/concert', null];
        yield 'something after the id' => ['/toulouse/soiree/concert--123/edit', null];
    }

    #[DataProvider('providePaths')]
    public function testFindsTheEventOfAPath(string $path, ?int $expected): void
    {
        self::assertSame($expected, EventVisitSync::eventIdOf($path));
    }

    /**
     * @param list<list<array{x: string, y: int}>>            $metricPages the metrics' pages, in order
     * @param list<array{eventName: string, urlPath: string}> $events
     *
     * @return array{events: int, visitors: int, ticketClicks: int}
     */
    private function sync(array $metricPages, array $events): array
    {
        $client = new MockHttpClient(function (string $method, string $url) use (&$metricPages, $events): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url];

            return match (true) {
                str_ends_with($url, '/api/auth/login') => new JsonMockResponse(['token' => 'secret-token']),
                str_contains($url, '/metrics?') => new JsonMockResponse(array_shift($metricPages) ?? []),
                str_contains($url, '/events?') => new JsonMockResponse(['data' => $events, 'count' => \count($events), 'page' => 1, 'pageSize' => 500]),
                default => new MockResponse('', ['http_code' => 404]),
            };
        }, 'https://umami.test/');

        $sync = new EventVisitSync(new UmamiClient($client, self::WEBSITE_ID, 'by-night', 'password'), $this->repository());

        return $sync->sync(new DateTimeImmutable('2026-10-20 15:00'));
    }

    private function repository(): EventVisitStatRepository
    {
        return self::getContainer()->get(EventVisitStatRepository::class);
    }
}
