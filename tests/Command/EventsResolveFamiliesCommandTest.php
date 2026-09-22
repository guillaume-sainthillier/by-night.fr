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
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end test of the catch-up command for rows imported before the identity hash
 * existed: hashes are computed from the stored columns, then the families they reveal
 * are resolved exactly as the import would.
 */
final class EventsResolveFamiliesCommandTest extends AppKernelTestCase
{
    private const string DESCRIPTION = 'Un atelier de poterie pour découvrir le tour, ouvert à tous les niveaux.';

    private EntityManagerInterface $entityManager;

    private Place $place;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $this->place = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $city, 'country' => $country]);
        $this->user = UserFactory::createOne();
    }

    public function testBackfillsTheIdentityHashAndResolvesTheFamiliesItReveals(): void
    {
        // The same workshop under three uids, and an unrelated concert, all without hash
        $firstId = $this->legacyRow('oa-1', '2026-10-03', 'Atelier poterie');
        $secondId = $this->legacyRow('oa-2', '2026-10-10', 'Atelier poterie');
        $thirdId = $this->legacyRow('oa-3', '2026-10-17', 'Atelier poterie');
        $concertId = $this->legacyRow('oa-4', '2026-10-03', 'Concert de jazz');

        // A user-created event has no origin, hence no identity, whatever its name
        $userEventId = EventFactory::createOne([
            'externalId' => null,
            'externalOrigin' => null,
            'identityHash' => null,
            'name' => 'Atelier poterie',
            'description' => self::DESCRIPTION,
            'placeExternalId' => 'loc-1',
            'place' => $this->place,
            'user' => $this->user,
        ])->getId();

        $output = $this->runResolveFamilies(['--origin' => 'openagenda']);

        $first = $this->reload($firstId);
        $second = $this->reload($secondId);
        $third = $this->reload($thirdId);
        $concert = $this->reload($concertId);

        self::assertNotNull($first->getIdentityHash());
        self::assertSame($first->getIdentityHash(), $second->getIdentityHash());
        self::assertSame($first->getIdentityHash(), $third->getIdentityHash());
        self::assertNotNull($concert->getIdentityHash());
        self::assertNotSame($first->getIdentityHash(), $concert->getIdentityHash());
        self::assertNull($this->reload($userEventId)->getIdentityHash());

        self::assertNull($first->getDuplicateOf(), 'The oldest row is the canonical.');
        self::assertSame($firstId, $second->getDuplicateOf()?->getId());
        self::assertSame($firstId, $third->getDuplicateOf()?->getId());
        self::assertNull($concert->getDuplicateOf());

        self::assertSame(
            ['2026-10-03' => null, '2026-10-10' => $secondId, '2026-10-17' => $thirdId],
            $this->datesBySource($first),
        );
        self::assertSame('2026-10-03', $first->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-10-17', $first->getEndDate()?->format('Y-m-d'));

        self::assertStringContainsString('4 event(s) hashed', $output);
        self::assertStringContainsString('1 families resolved', $output);
    }

    public function testSkipBackfillOnlyResolvesTheRowsAlreadyHashed(): void
    {
        $hash = sha1('hashed workshop');
        $hashedAId = $this->legacyRow('oa-1', '2026-10-03', 'Atelier poterie', $hash);
        $hashedBId = $this->legacyRow('oa-2', '2026-10-10', 'Atelier poterie', $hash);
        $bareAId = $this->legacyRow('oa-3', '2026-10-03', 'Concert de jazz');
        $bareBId = $this->legacyRow('oa-4', '2026-10-10', 'Concert de jazz');

        $this->runResolveFamilies(['--skip-backfill' => true]);

        self::assertSame($hashedAId, $this->reload($hashedBId)->getDuplicateOf()?->getId());
        self::assertNull($this->reload($bareAId)->getIdentityHash());
        self::assertNull($this->reload($bareBId)->getIdentityHash());
        self::assertNull($this->reload($bareBId)->getDuplicateOf());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runResolveFamilies(array $input): string
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:events:resolve-families'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        $this->entityManager->clear();

        return $tester->getDisplay();
    }

    /**
     * A row imported before the identity hash existed, with one timesheet of its own.
     */
    private function legacyRow(string $externalId, string $date, string $name, ?string $identityHash = null): int
    {
        $event = EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => 'openagenda',
            'identityHash' => $identityHash,
            'name' => $name,
            'description' => self::DESCRIPTION,
            'placeExternalId' => 'loc-1',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($date),
            'hours' => 'À 20h30',
        ]);
        EventTimesheetFactory::new()->on($date, 'À 20h30')->create(['event' => $event]);

        return (int) $event->getId();
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }

    /**
     * @return array<string, int|null>
     */
    private function datesBySource(Event $event): array
    {
        $dates = [];
        foreach ($event->getTimesheets() as $timesheet) {
            $dates[(string) $timesheet->getStartAt()?->format('Y-m-d')] = $timesheet->getSourceEvent()?->getId();
        }

        ksort($dates);

        return $dates;
    }
}
