<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\CommentFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end tests for the content merge strategy (the one that handles affiliate
 * feeds such as Fnac, where every ticket product lands as a distinct external id).
 *
 * The suffix strategy is intentionally not covered here: its candidate query relies
 * on the MySQL REGEXP function, which the SQLite test database does not provide.
 */
final class EventsMergeDuplicatesCommandIntegrationTest extends AppKernelTestCase
{
    private const string ORIGIN = 'awin.fnac';

    private Place $place;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Duplicates of one show share a single venue. Building it explicitly (rather
        // than letting EventFactory spawn a random Place per event) keeps the country
        // code fixed and avoids identity-map collisions on Country's app-assigned PK.
        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Avignon', 'country' => $country]);
        $this->place = PlaceFactory::createOne([
            'name' => 'Théâtre Buffon',
            'city' => $city,
            'country' => $country,
        ]);
        $this->user = UserFactory::createOne();
    }

    public function testContentStrategyMergesLegacyDuplicatesIntoTheOldestEvent(): void
    {
        // Three ticket products of the same show, no timesheets (legacy import shape),
        // each with its own date and showtime. Created in id order, so "2001" is oldest.
        $this->fnacEvent('2001', new DateTimeImmutable('2026-07-03'), 'À 13h00');
        $this->fnacEvent('2002', new DateTimeImmutable('2026-07-01'), 'À 18h00');
        $this->fnacEvent('2003', new DateTimeImmutable('2026-07-02'), 'À 20h00');

        $this->runMerge(['--strategy' => 'content', '--origin' => self::ORIGIN]);

        $canonical = $this->find('2001');
        $duplicateA = $this->find('2002');
        $duplicateB = $this->find('2003');

        // The oldest event survives; the others point at it.
        self::assertNull($canonical->getDuplicateOf());
        self::assertSame($canonical->getId(), $duplicateA->getDuplicateOf()?->getId());
        self::assertSame($canonical->getId(), $duplicateB->getDuplicateOf()?->getId());

        // Every date became a timesheet on the canonical, each keeping its showtime,
        // and the canonical's range was realigned to span them.
        self::assertSame(
            ['2026-07-01', '2026-07-02', '2026-07-03'],
            $this->timesheetDates($canonical),
        );
        self::assertSame(
            ['2026-07-01' => 'À 18h00', '2026-07-02' => 'À 20h00', '2026-07-03' => 'À 13h00'],
            $this->timesheetHoursByDate($canonical),
        );
        self::assertSame('2026-07-01', $canonical->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-07-03', $canonical->getEndDate()?->format('Y-m-d'));
    }

    public function testContentStrategyKeepsTheContentHashEventAsCanonical(): void
    {
        $hashId = sha1('Van Gogh|place-hash');

        // The stable hash event is created *after* a legacy one, so it is not the
        // oldest, yet it must still win as the canonical.
        $this->fnacEvent('3001', new DateTimeImmutable('2026-07-05'), 'À 20h00');
        $this->fnacEvent($hashId, new DateTimeImmutable('2026-07-06'), 'À 20h00');
        $this->fnacEvent('3002', new DateTimeImmutable('2026-07-07'), 'À 20h00');

        $this->runMerge(['--strategy' => 'content', '--origin' => self::ORIGIN]);

        $canonical = $this->find($hashId);
        self::assertNull($canonical->getDuplicateOf());
        self::assertSame($canonical->getId(), $this->find('3001')->getDuplicateOf()?->getId());
        self::assertSame($canonical->getId(), $this->find('3002')->getDuplicateOf()?->getId());
    }

    public function testDryRunLeavesEverythingUntouched(): void
    {
        $this->fnacEvent('4001', new DateTimeImmutable('2026-07-01'), 'À 18h00');
        $this->fnacEvent('4002', new DateTimeImmutable('2026-07-02'), 'À 18h00');

        $this->runMerge(['--strategy' => 'content', '--origin' => self::ORIGIN, '--dry-run' => true]);

        // A dry run plans the merge in memory but must persist nothing, so both events
        // keep a null duplicateOf in the database. Asserted with a fresh count() query
        // rather than find(): the dry run leaves its unflushed changes in the manager,
        // which would otherwise be read back instead of the untouched persisted state.
        self::assertSame(2, EventFactory::count());
        self::assertSame(2, EventFactory::count(['duplicateOf' => null]));
    }

    public function testExactStrategyKeepsTheLatestUpdatedRowAndStripsTheStubIdentity(): void
    {
        // Exact duplicates predate the unique key on (external_id, external_origin): drop it
        // for the fixtures. SQLite DDL is transactional, the test transaction restores it.
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement($connection->getDatabasePlatform()->getDropIndexSQL('event_external_id_unique', 'event'));

        // The same Fnac product imported twice, no source timestamp on this feed: the row
        // we updated last is the one that kept tracking the source. It is created first,
        // so the id fallback would pick the wrong row if the timestamps were ignored.
        $trackedId = $this->exactDuplicate('00047', new DateTimeImmutable('2022-06-29 18:41:53'));
        $fossilId = $this->exactDuplicate('00047', new DateTimeImmutable('2020-03-18 06:07:12'));
        $commentId = CommentFactory::createOne(['event' => EventFactory::find(['id' => $fossilId]), 'user' => $this->user])->getId();
        $chainedId = EventFactory::createOne([
            'externalId' => '00047-1',
            'externalOrigin' => self::ORIGIN,
            'name' => 'Van Gogh',
            'place' => $this->place,
            'user' => $this->user,
            'duplicateOf' => EventFactory::find(['id' => $fossilId]),
        ])->getId();

        $this->runMerge(['--strategy' => 'exact', '--origin' => self::ORIGIN]);

        $keeper = EventFactory::find(['id' => $trackedId]);
        $stub = EventFactory::find(['id' => $fossilId]);

        self::assertNull($keeper->getDuplicateOf());
        self::assertSame('00047', $keeper->getExternalId());
        self::assertSame($keeper->getId(), $stub->getDuplicateOf()?->getId());

        // The stub gives up the identity: the unique key holds and the next import lands on the keeper
        self::assertNull($stub->getExternalId());
        self::assertNull($stub->getExternalOrigin());
        self::assertSame(1, EventFactory::count(['externalId' => '00047', 'externalOrigin' => self::ORIGIN]));

        // Comments follow the keeper, and redirects are re-pointed rather than chained
        self::assertSame($keeper->getId(), CommentFactory::find(['id' => $commentId])->getEvent()?->getId());
        self::assertSame($keeper->getId(), EventFactory::find(['id' => $chainedId])->getDuplicateOf()?->getId());
    }

    public function testExactStrategyStripsTheIdentityOfStubsThatStillShareIt(): void
    {
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement($connection->getDatabasePlatform()->getDropIndexSQL('event_external_id_unique', 'event'));

        // A content merge from before left a redirect stub that kept its identity, and a
        // later import created a live row under the very same identity.
        $liveId = $this->exactDuplicate('00099', new DateTimeImmutable('2022-06-29 18:41:53'));
        $otherCanonicalId = $this->exactDuplicate('00098', new DateTimeImmutable('2022-06-29 18:41:53'));
        $stubId = EventFactory::createOne([
            'externalId' => '00099',
            'externalOrigin' => self::ORIGIN,
            'name' => 'A2H',
            'place' => $this->place,
            'user' => $this->user,
            'updatedAt' => new DateTimeImmutable('2024-01-01'),
            'duplicateOf' => EventFactory::find(['id' => $otherCanonicalId]),
        ])->getId();

        $this->runMerge(['--strategy' => 'exact', '--origin' => self::ORIGIN]);

        // The live row keeps the identity even though the stub was updated more recently
        $live = EventFactory::find(['id' => $liveId]);
        self::assertNull($live->getDuplicateOf());
        self::assertSame('00099', $live->getExternalId());

        // The stub loses it and still redirects where it did
        $stub = EventFactory::find(['id' => $stubId]);
        self::assertNull($stub->getExternalId());
        self::assertNull($stub->getExternalOrigin());
        self::assertSame($otherCanonicalId, $stub->getDuplicateOf()?->getId());
        self::assertSame(1, EventFactory::count(['externalId' => '00099', 'externalOrigin' => self::ORIGIN]));
    }

    /**
     * Create one of two rows sharing the exact same external identity.
     */
    private function exactDuplicate(string $externalId, DateTimeImmutable $updatedAt): int
    {
        return EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => self::ORIGIN,
            'name' => 'A2H',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable('2026-07-01'),
            'endDate' => new DateTimeImmutable('2026-07-01'),
            'updatedAt' => $updatedAt,
        ])->getId();
    }

    /**
     * Create a Fnac-shaped event sharing one show identity (origin + name + place).
     */
    private function fnacEvent(string $externalId, DateTimeImmutable $date, ?string $hours): void
    {
        EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => self::ORIGIN,
            'name' => 'Van Gogh',
            'placeExternalId' => 'place-hash',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => $date,
            'endDate' => $date,
            'hours' => $hours,
        ]);
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function runMerge(array $input): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:events:merge-duplicates'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();
    }

    private function find(string $externalId): Event
    {
        // The command clears the EntityManager; the proxy returned here auto-refreshes
        // from the database on access, so assertions read the persisted state fresh.
        return EventFactory::find([
            'externalId' => $externalId,
            'externalOrigin' => self::ORIGIN,
        ]);
    }

    /**
     * @return list<string>
     */
    private function timesheetDates(Event $event): array
    {
        $dates = array_map(
            static fn ($timesheet): ?string => $timesheet->getStartAt()?->format('Y-m-d'),
            $event->getTimesheets()->toArray(),
        );
        sort($dates);

        return $dates;
    }

    /**
     * @return array<string, string|null>
     */
    private function timesheetHoursByDate(Event $event): array
    {
        $hoursByDate = [];
        foreach ($event->getTimesheets() as $timesheet) {
            $date = $timesheet->getStartAt()?->format('Y-m-d') ?? '';
            $hoursByDate[$date] = $timesheet->getHours();
        }

        ksort($hoursByDate);

        return $hoursByDate;
    }
}
