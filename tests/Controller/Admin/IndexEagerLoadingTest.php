<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Admin;

use App\Factory\AdminZone1Factory;
use App\Factory\CityFactory;
use App\Factory\CommentFactory;
use App\Factory\ContentRemovalRequestFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use Closure;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The associations an index lists are loaded for the whole page by the repository's
 * loadAllEager() (App\Admin\EagerLoadingEntityPaginator), not once per row.
 */
final class IndexEagerLoadingTest extends WebTestCase
{
    /**
     * @param Closure(): object $createRow
     */
    #[DataProvider('provideIndexes')]
    public function testNoQueryRunsOncePerRow(string $path, Closure $createRow): void
    {
        // createClient() first: factories boot the kernel and WebTestCase refuses a late client
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create());
        // Every row before the first request: the client reboots the kernel before the next ones,
        // and Faker's unique() does not survive a reboot (country codes and tag names collide)
        for ($i = 0; $i < 4; ++$i) {
            $createRow();
        }

        // The first request runs on the kernel the fixtures were built with, all of them in its
        // identity map: the measured one, on a fresh kernel, loads every association it lists
        $client->request('GET', $path);
        $client->enableProfiler();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        // A lazy load per row runs the same statement for each of the four rows
        $repeated = array_filter($collector->getGroupedQueries()['default'] ?? [], static fn (array $query): bool => $query['count'] > 1);
        self::assertSame([], array_column($repeated, 'sql'));
    }

    /**
     * @return iterable<string, array{string, Closure(): object}>
     */
    public static function provideIndexes(): iterable
    {
        yield 'events and their place and member' => ['/_administration/event', static fn (): object => EventFactory::createOne()];
        // A city is named after its region, which Doctrine loads on its own unless joined
        yield 'places and their city and region' => ['/_administration/place', static fn (): object => PlaceFactory::createOne([
            'city' => CityFactory::new(['parent' => AdminZone1Factory::new()]),
        ])];
        yield 'comments and their event and member' => ['/_administration/comment', static fn (): object => CommentFactory::createOne()];
        yield 'dates and their event and source event' => ['/_administration/event-timesheet', static fn (): object => EventTimesheetFactory::createOne([
            'sourceEvent' => EventFactory::new(),
        ])];
        yield 'participations and their event and member' => ['/_administration/user-event', static fn (): object => UserEventFactory::createOne()];
        yield 'removal requests and their events' => ['/_administration/content-removal-request', static fn (): object => ContentRemovalRequestFactory::createOne([
            'events' => EventFactory::new()->many(2),
        ])];
    }
}
