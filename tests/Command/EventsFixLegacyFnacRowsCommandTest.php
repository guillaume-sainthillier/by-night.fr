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
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Import\EventFamilyResolver;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The Fnac rows of the per-product scheme (before #407) redirect to the row the feed still updates, and end their
 * sessions the day they start: the parser of the time read the end of the ticket sale as the end of the show.
 */
final class EventsFixLegacyFnacRowsCommandTest extends AppKernelTestCase
{
    private const string LEGACY_HASH = 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3';

    private const string CURRENT_HASH = '0ccdcd8e0adede5fa6957c23a015c38a641cf028';

    public function testALegacyCanonicalRedirectsToTheCurrentRow(): void
    {
        // The description changed since the legacy rows were imported: other identity, other family, two pages
        $zenith = PlaceFactory::createOne();
        $legacyId = $this->row('19391413', $zenith, [['2030-04-11', '2030-07-03']]);
        $otherLegacyId = $this->row('19391428', $zenith, [['2030-06-12', '2030-07-03']]);
        $currentId = $this->row(sha1('Le Roi Soleil|zenith'), $zenith, [['2030-06-12', '2030-06-12']], self::CURRENT_HASH);
        $this->resolveFamilies([$legacyId, $otherLegacyId, $currentId]);
        self::assertSame($legacyId, $this->reload($otherLegacyId)->getDuplicateOf()?->getId());
        self::assertNull($this->reload($currentId)->getDuplicateOf(), 'The show has two pages.');

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        $current = $this->reload($currentId);
        self::assertNull($current->getDuplicateOf());
        self::assertSame(['2030-06-12→2030-06-12' => null], $this->sessions($current), 'No legacy date is inherited.');
        self::assertSame(['2030-06-12', '2030-06-12'], $this->range($current));
        foreach ([$legacyId, $otherLegacyId] as $id) {
            $legacy = $this->reload($id);
            self::assertSame($currentId, $legacy->getDuplicateOf()?->getId(), 'A legacy row redirects to the current one, not to a redirect.');
            self::assertNull($legacy->getIdentityHash(), 'A legacy row lends no date any more.');
        }

        self::assertSame(['2030-04-11→2030-04-11' => null], $this->sessions($this->reload($legacyId)), 'The former canonical keeps its own session, ended the day it starts.');
        self::assertStringContainsString('1 legacy row(s) redirected to their current row (with 1 row(s) pointing at them), 0 left alone', $display);
    }

    public function testACurrentRowPointingAtTheLegacyOneTakesOver(): void
    {
        $place = PlaceFactory::createOne();
        $legacyId = $this->row('21269504', $place, [['2030-10-03', '2030-11-04']]);
        $currentId = $this->row(sha1('Le Morpion|place'), $place, [['2030-10-17', '2030-10-17']]);
        $this->resolveFamilies([$legacyId, $currentId]);
        self::assertSame($legacyId, $this->reload($currentId)->getDuplicateOf()?->getId(), 'Same identity: the oldest row heads the family.');

        $this->doRunCommand(['--apply' => true]);

        $current = $this->reload($currentId);
        self::assertNull($current->getDuplicateOf());
        self::assertSame(['2030-10-17→2030-10-17' => null], $this->sessions($current));
        self::assertSame($currentId, $this->reload($legacyId)->getDuplicateOf()?->getId());
    }

    public function testALegacyRowWithoutCurrentRowKeepsItsPageWithItsPerformanceDates(): void
    {
        $orphanId = $this->row('20115532', PlaceFactory::createOne(), [['2030-04-11', '2030-07-03'], ['2030-06-12', '2030-07-03']]);
        // Before the timesheets: a range only
        $rangeOnlyId = $this->row('20115533', PlaceFactory::createOne(), [], sha1('range only'), dates: ['2030-05-01', '2030-06-01']);

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        $orphan = $this->reload($orphanId);
        self::assertNull($orphan->getDuplicateOf());
        self::assertSame(['2030-04-11→2030-04-11' => null, '2030-06-12→2030-06-12' => null], $this->sessions($orphan));
        self::assertSame(['2030-04-11', '2030-06-12'], $this->range($orphan));
        self::assertSame(['2030-05-01', '2030-05-01'], $this->range($this->reload($rangeOnlyId)));
        self::assertStringContainsString('2 legacy row(s) given their performance dates', $display);
    }

    public function testAShowWithSeveralCurrentRowsIsLeftAlone(): void
    {
        // Two spellings of the address, merged into one place: no telling which row the legacy one is
        $place = PlaceFactory::createOne();
        $legacyId = $this->row('21528264', $place, [['2030-06-12', '2030-06-12']]);
        $this->row(sha1('Le Roi Soleil|zenith'), $place, [['2030-06-12', '2030-06-12']], self::CURRENT_HASH);
        $this->row(sha1('Le Roi Soleil|zénith'), $place, [['2030-06-12', '2030-06-12']], sha1('other'));

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        self::assertNull($this->reload($legacyId)->getDuplicateOf());
        self::assertSame(self::LEGACY_HASH, $this->reload($legacyId)->getIdentityHash());
        self::assertStringContainsString('0 legacy row(s) redirected to their current row (with 0 row(s) pointing at them), 1 left alone', $display);
    }

    public function testARunLongOverKeepsItsPage(): void
    {
        // Same name, same place, years apart: another production
        $place = PlaceFactory::createOne();
        $pastId = $this->row('790355', $place, [['2019-11-08', '2019-11-30']]);
        $this->row(sha1('Pygmalion|place'), $place, [['2030-02-14', '2030-02-14']], self::CURRENT_HASH);

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        self::assertNull($this->reload($pastId)->getDuplicateOf());
        self::assertSame(self::LEGACY_HASH, $this->reload($pastId)->getIdentityHash());
        self::assertStringContainsString('0 legacy row(s) redirected to their current row (with 0 row(s) pointing at them), 0 left alone', $display);
    }

    public function testOtherRowsAreLeftAlone(): void
    {
        // Over: their dates show nowhere any more
        $pastId = $this->row('18000001', PlaceFactory::createOne(), [['2020-04-11', '2020-07-03']]);
        // The current scheme, and another source, take their sessions from their feed
        $currentId = $this->row(sha1('Festival|place'), PlaceFactory::createOne(), [['2030-07-01', '2030-07-03']], self::CURRENT_HASH);
        $openAgendaId = $this->row('41234567', PlaceFactory::createOne(), [['2030-04-11', '2030-07-03']], sha1('workshop'), 'openagenda');

        $this->doRunCommand(['--apply' => true]);

        self::assertSame(['2020-04-11→2020-07-03' => null], $this->sessions($this->reload($pastId)));
        self::assertSame(['2030-07-01→2030-07-03' => null], $this->sessions($this->reload($currentId)));
        self::assertSame(['2030-04-11→2030-07-03' => null], $this->sessions($this->reload($openAgendaId)));
    }

    public function testPreviewWritesNothing(): void
    {
        $place = PlaceFactory::createOne();
        $legacyId = $this->row('21269507', $place, [['2030-10-03', '2030-11-04']]);
        $currentId = $this->row(sha1('Les Justes|place'), $place, [['2030-10-10', '2030-10-10']], self::CURRENT_HASH);

        $display = self::unwrap($this->doRunCommand([])->getDisplay());

        self::assertStringContainsString('1 legacy row(s) would be redirected to their current row', $display);
        self::assertStringContainsString('1 legacy row(s) would be given their performance dates', $display);
        $legacy = $this->reload($legacyId);
        self::assertNull($legacy->getDuplicateOf());
        self::assertSame(self::LEGACY_HASH, $legacy->getIdentityHash());
        self::assertSame(['2030-10-03→2030-11-04' => null], $this->sessions($legacy));
        self::assertNull($this->reload($currentId)->getDuplicateOf());
    }

    /**
     * A Fnac row of the show, with the given own sessions (start and end days), its range spanning them.
     *
     * @param list<array{string, string}> $sessions
     * @param array{string, string}|null  $dates    the range of a row without sessions
     */
    private function row(string $externalId, Place $place, array $sessions, string $identityHash = self::LEGACY_HASH, string $origin = 'awin.fnac', ?array $dates = null): int
    {
        $dates ??= [min(array_column($sessions, 0)), max(array_column($sessions, 1))];
        $event = EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => $origin,
            'identityHash' => $identityHash,
            'name' => 'Le Roi Soleil - Tournée',
            'place' => $place,
            'startDate' => new DateTimeImmutable($dates[0]),
            'endDate' => new DateTimeImmutable($dates[1]),
        ]);
        foreach ($sessions as [$startAt, $endAt]) {
            EventTimesheetFactory::createOne(['event' => $event, 'startAt' => new DateTimeImmutable($startAt), 'endAt' => new DateTimeImmutable($endAt)]);
        }

        return (int) $event->getId();
    }

    /**
     * The families as the import left them.
     *
     * @param int[] $ids
     */
    private function resolveFamilies(array $ids): void
    {
        self::getContainer()->get(EventFamilyResolver::class)->resolveForEvents($ids);
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }

    /**
     * The event's sessions, each with the id of the sibling it was inherited from (null for its own).
     *
     * @return array<string, int|null>
     */
    private function sessions(Event $event): array
    {
        $sessions = [];
        foreach ($event->getTimesheets() as $timesheet) {
            $sessions[\sprintf('%s→%s', $timesheet->getStartAt()?->format('Y-m-d'), $timesheet->getEndAt()?->format('Y-m-d'))] = $timesheet->getSourceEvent()?->getId();
        }

        ksort($sessions);

        return $sessions;
    }

    /**
     * @return array{string|null, string|null}
     */
    private function range(Event $event): array
    {
        return [$event->getStartDate()?->format('Y-m-d'), $event->getEndDate()?->format('Y-m-d')];
    }

    /**
     * The output on one line: SymfonyStyle wraps the notes at the terminal width.
     */
    private static function unwrap(string $display): string
    {
        return (string) preg_replace('/\s*\n\s*!?\s*/', ' ', $display);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:fix-legacy-fnac-rows'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
