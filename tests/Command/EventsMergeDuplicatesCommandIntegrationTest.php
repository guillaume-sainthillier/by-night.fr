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
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Repository\EventRepository;
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

    private EntityManagerInterface $entityManager;

    private EventRepository $eventRepository;

    private Place $place;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->eventRepository = self::getContainer()->get(EventRepository::class);

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
        // each with its own date. Created in id order, so "2001" is the oldest.
        $this->fnacEvent('2001', new DateTimeImmutable('2026-07-03'), 'À 13h00');
        $this->fnacEvent('2002', new DateTimeImmutable('2026-07-01'), 'À 13h00');
        $this->fnacEvent('2003', new DateTimeImmutable('2026-07-02'), 'À 13h00');

        $this->runMerge(['--strategy' => 'content', '--origin' => self::ORIGIN]);

        $canonical = $this->find('2001');
        $duplicateA = $this->find('2002');
        $duplicateB = $this->find('2003');

        // The oldest event survives; the others point at it.
        self::assertNull($canonical->getDuplicateOf());
        self::assertSame($canonical->getId(), $duplicateA->getDuplicateOf()?->getId());
        self::assertSame($canonical->getId(), $duplicateB->getDuplicateOf()?->getId());

        // Every date became a timesheet on the canonical, and its range was realigned.
        self::assertSame(
            ['2026-07-01', '2026-07-02', '2026-07-03'],
            $this->timesheetDates($canonical),
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

        self::assertNull($this->find('4001')->getDuplicateOf());
        self::assertNull($this->find('4002')->getDuplicateOf());
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

        // The command clears the EntityManager; drop any stale identity map so the
        // assertions below read the persisted state fresh.
        $this->entityManager->clear();
    }

    private function find(string $externalId): Event
    {
        $event = $this->eventRepository->findOneBy([
            'externalId' => $externalId,
            'externalOrigin' => self::ORIGIN,
        ]);

        self::assertInstanceOf(Event::class, $event);

        return $event;
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
}
