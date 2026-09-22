<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Import\EventFamilyResolver;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

/**
 * Integration tests for the family resolution: rows describing the same event under
 * distinct external ids (an OpenAgenda organizer creating one event per session).
 */
final class EventFamilyResolverTest extends AppKernelTestCase
{
    private const string HASH = 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3';

    private EventFamilyResolver $resolver;

    private EntityManagerInterface $entityManager;

    private Place $place;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = self::getContainer()->get(EventFamilyResolver::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // One venue and one user for every row, so Country's app-assigned PK is created once
        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $this->place = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $city, 'country' => $country]);
        $this->user = UserFactory::createOne();
    }

    public function testSiblingsSharingAnIdentityFormAFamilyAroundTheOldestRow(): void
    {
        $firstId = $this->sibling('oa-1', '2026-10-03');
        $secondId = $this->sibling('oa-2', '2026-10-10');

        $this->resolve([$secondId]);

        $canonical = $this->reload($firstId);
        $duplicate = $this->reload($secondId);

        self::assertNull($canonical->getDuplicateOf(), 'The oldest row is the canonical.');
        self::assertSame($firstId, $duplicate->getDuplicateOf()?->getId());
        self::assertFalse($duplicate->isIndexable(), 'Only the canonical is listed.');

        // The canonical shows both dates: its own, and one inherited from the sibling
        self::assertSame(['2026-10-03' => null, '2026-10-10' => $secondId], $this->datesBySource($canonical));
        self::assertSame('2026-10-03', $canonical->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-10-10', $canonical->getEndDate()?->format('Y-m-d'));

        // The sibling keeps its own row only
        self::assertSame(['2026-10-10' => null], $this->datesBySource($duplicate));
    }

    public function testInheritedDatesFollowTheSiblingsOwnDates(): void
    {
        $firstId = $this->sibling('oa-1', '2026-10-03');
        $secondId = $this->sibling('oa-2', '2026-10-10');
        $this->resolve([$secondId]);

        // The sibling is re-imported with its session moved: its own row is replaced
        $duplicate = $this->reload($secondId);
        foreach ($duplicate->getOwnTimesheets() as $timesheet) {
            $duplicate->removeTimesheet($timesheet);
        }

        $this->entityManager->flush();
        EventTimesheetFactory::new()->on('2026-10-11', 'À 20h30')->create(['event' => $duplicate]);

        $this->resolve([$secondId]);

        $canonical = $this->reload($firstId);
        self::assertSame(
            ['2026-10-03' => null, '2026-10-11' => $secondId],
            $this->datesBySource($canonical),
            'The stale inherited date is gone, the new one is there.',
        );
        self::assertSame('2026-10-11', $canonical->getEndDate()?->format('Y-m-d'));
    }

    public function testInheritingADateMarksTheCanonicalUpdatedForTheSearchIndex(): void
    {
        $firstId = $this->sibling('oa-1', '2026-10-03');
        $secondId = $this->sibling('oa-2', '2026-10-10');
        $this->reload($firstId)->setUpdatedAt(new DateTimeImmutable('2020-01-01'));
        $this->entityManager->flush();

        $this->resolve([$secondId]);

        // The listener that refreshes the index only watches the event row
        self::assertGreaterThan(new DateTimeImmutable('2020-01-02'), $this->reload($firstId)->getUpdatedAt());
    }

    public function testResolutionIsIdempotent(): void
    {
        $firstId = $this->sibling('oa-1', '2026-10-03');
        $secondId = $this->sibling('oa-2', '2026-10-10');

        $this->resolve([$firstId, $secondId]);
        $inheritedId = $this->inheritedTimesheetId($firstId);
        self::assertNotNull($inheritedId);

        $this->reload($firstId)->setUpdatedAt(new DateTimeImmutable('2020-01-01'));
        $this->entityManager->flush();

        $this->resolve([$firstId, $secondId]);
        $this->resolve([$firstId]);

        self::assertSame($inheritedId, $this->inheritedTimesheetId($firstId), 'An unchanged inherited row is kept, not recreated.');
        self::assertSame('2020-01-01', $this->reload($firstId)->getUpdatedAt()?->format('Y-m-d'), 'Nothing changed, so the search index is not asked to refresh.');
        self::assertCount(2, $this->reload($firstId)->getTimesheets());
        self::assertCount(1, $this->reload($secondId)->getTimesheets());
    }

    public function testARowLeavingTheFamilyTakesItsDatesWithIt(): void
    {
        $firstId = $this->sibling('oa-1', '2026-10-03');
        $secondId = $this->sibling('oa-2', '2026-10-10');
        $this->resolve([$secondId]);

        // The organizer rewrote the second event: it describes something else now
        $this->reload($secondId)->setIdentityHash(sha1('something else'));
        $this->entityManager->flush();

        $this->resolve([$secondId]);

        $canonical = $this->reload($firstId);
        $freed = $this->reload($secondId);

        self::assertNull($freed->getDuplicateOf());
        self::assertSame(['2026-10-10' => null], $this->datesBySource($freed));
        self::assertSame(['2026-10-03' => null], $this->datesBySource($canonical));
        self::assertSame('2026-10-03', $canonical->getEndDate()?->format('Y-m-d'), 'The range shrinks back to the canonical\'s own date.');
    }

    public function testTheCurrentCanonicalKeepsItsRoleOverAnOlderDuplicate(): void
    {
        // Linked the other way round than the id order, as the merge command may have done
        $olderId = $this->sibling('oa-1', '2026-10-03');
        $newerId = $this->sibling('oa-2', '2026-10-10');
        $this->reload($olderId)->setDuplicateOf($this->reload($newerId));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->resolver->resolveFamilies([self::HASH]);
        $this->entityManager->clear();

        self::assertNull($this->reload($newerId)->getDuplicateOf(), 'Public URLs stay put: the current canonical is not demoted.');
        self::assertSame($newerId, $this->reload($olderId)->getDuplicateOf()?->getId());
        self::assertSame(['2026-10-03' => $olderId, '2026-10-10' => null], $this->datesBySource($this->reload($newerId)));
    }

    public function testLegacyLinksWithoutIdentityAreLeftAlone(): void
    {
        $canonicalId = $this->sibling('oa-1', '2026-10-03');

        // A stub left by the exact merge strategy: no identity of its own, a fossil date
        $stubId = EventFactory::createOne([
            'externalId' => null,
            'externalOrigin' => null,
            'identityHash' => null,
            'name' => 'Atelier poterie',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable('2020-01-01'),
            'endDate' => new DateTimeImmutable('2020-01-01'),
            'duplicateOf' => $this->reload($canonicalId),
        ])->getId();

        $this->resolve([$canonicalId]);

        self::assertSame($canonicalId, $this->reload($stubId)->getDuplicateOf()?->getId(), 'The redirect survives.');
        self::assertSame(['2026-10-03' => null], $this->datesBySource($this->reload($canonicalId)), 'The fossil date is not resurrected.');
    }

    public function testACanonicalWithoutTimesheetsKeepsItsOwnDateInTheUnion(): void
    {
        // Imported by a parser that only sets a date range, no timesheet rows
        $firstId = $this->sibling('oa-1', '2026-10-03', withTimesheet: false);
        $secondId = $this->sibling('oa-2', '2026-10-10', withTimesheet: false);

        $this->resolve([$secondId]);

        $canonical = $this->reload($firstId);
        self::assertSame(['2026-10-03' => null, '2026-10-10' => $secondId], $this->datesBySource($canonical));
        self::assertSame('2026-10-03', $canonical->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-10-10', $canonical->getEndDate()?->format('Y-m-d'));
    }

    public function testRowsWithoutAFamilyAreLeftUntouched(): void
    {
        $aloneId = $this->sibling('oa-1', '2026-10-03');
        $otherId = EventFactory::createOne([
            'externalId' => 'oa-2',
            'externalOrigin' => 'openagenda',
            'identityHash' => sha1('another event'),
            'name' => 'Concert de jazz',
            'placeExternalId' => 'loc-1',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable('2026-10-03'),
            'endDate' => new DateTimeImmutable('2026-10-03'),
        ])->getId();

        $this->resolve([$aloneId, $otherId]);

        self::assertNull($this->reload($aloneId)->getDuplicateOf());
        self::assertNull($this->reload($otherId)->getDuplicateOf());
        self::assertSame(['2026-10-03' => null], $this->datesBySource($this->reload($aloneId)));
        self::assertCount(0, $this->reload($otherId)->getTimesheets());
    }

    /**
     * One record of the same workshop, under its own external id and on its own date.
     */
    private function sibling(string $externalId, string $date, bool $withTimesheet = true): int
    {
        $event = EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => 'openagenda',
            'identityHash' => self::HASH,
            'name' => 'Atelier poterie',
            'placeExternalId' => 'loc-1',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($date),
            'hours' => 'À 20h30',
        ]);

        if ($withTimesheet) {
            EventTimesheetFactory::new()->on($date, 'À 20h30')->create(['event' => $event]);
        }

        return (int) $event->getId();
    }

    /**
     * Run the resolver the way the import does: on a cleared manager, then forget everything.
     *
     * @param int[] $ids
     */
    private function resolve(array $ids): void
    {
        $this->entityManager->clear();
        $this->resolver->resolveForEvents($ids);
        $this->entityManager->clear();
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }

    /**
     * The event's timesheet dates, each with the id of the sibling it was inherited
     * from (null for the event's own rows).
     *
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

    private function inheritedTimesheetId(int $eventId): ?int
    {
        foreach ($this->reload($eventId)->getInheritedTimesheets() as $timesheet) {
            return $timesheet->getId();
        }

        return null;
    }
}
