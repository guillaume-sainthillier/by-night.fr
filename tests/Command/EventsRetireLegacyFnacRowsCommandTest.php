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
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Import\EventFamilyResolver;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A Fnac show whose family is headed by a row of the per-product scheme (before #407) is
 * handed to the row the feed still updates.
 */
final class EventsRetireLegacyFnacRowsCommandTest extends AppKernelTestCase
{
    private const string HASH = 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3';

    public function testTheCurrentRowTakesOverFromALegacyCanonical(): void
    {
        $legacyId = $this->row('21269502', ['2026-10-03'], '25.0€');
        $otherLegacyId = $this->row('21269503', ['2026-10-10'], '25.0€');
        $currentId = $this->row(sha1('Les Justes|place'), ['2026-10-10', '2026-10-17'], '27€');
        $this->resolveFamilies([$legacyId, $otherLegacyId, $currentId]);
        self::assertSame($legacyId, $this->reload($currentId)->getDuplicateOf()?->getId(), 'The backfill left the oldest row canonical.');

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        $current = $this->reload($currentId);
        self::assertNull($current->getDuplicateOf());
        self::assertSame('27€', $current->getPrices());
        foreach ([$legacyId, $otherLegacyId] as $id) {
            $legacy = $this->reload($id);
            self::assertSame($currentId, $legacy->getDuplicateOf()?->getId(), 'A legacy row redirects to the current one.');
            self::assertNull($legacy->getIdentityHash(), 'A legacy row lends no date any more.');
        }

        // The dates the feed sells now, none from the legacy rows
        self::assertSame(['2026-10-10' => null, '2026-10-17' => null], $this->datesBySource($current));
        self::assertSame(['2026-10-10', '2026-10-17'], [$current->getStartDate()?->format('Y-m-d'), $current->getEndDate()?->format('Y-m-d')]);
        self::assertSame(['2026-10-03' => null], $this->datesBySource($this->reload($legacyId)), 'The former canonical keeps its own date only.');

        self::assertStringContainsString('1 show(s) handed to their current row, 2 legacy row(s) redirected to it', $display);
    }

    public function testACurrentCanonicalKeepsItsRole(): void
    {
        $currentId = $this->row(sha1('Le Morpion|place'), ['2026-10-17'], '23€');
        $legacyId = $this->row('21269504', ['2026-10-03'], '23.0€');
        $this->resolveFamilies([$currentId, $legacyId]);

        $display = $this->doRunCommand(['--apply' => true])->getDisplay();

        $current = $this->reload($currentId);
        self::assertNull($current->getDuplicateOf());
        self::assertSame(['2026-10-17' => null], $this->datesBySource($current), 'The legacy date is no longer inherited.');
        self::assertSame($currentId, $this->reload($legacyId)->getDuplicateOf()?->getId());
        self::assertNull($this->reload($legacyId)->getIdentityHash());
        self::assertStringContainsString('0 show(s) handed to their current row, 1 legacy row(s) redirected to it', $display);
    }

    public function testFamiliesWithoutBothSchemesAreLeftAlone(): void
    {
        // Only legacy rows: no row to hand the show to
        $legacyId = $this->row('21269505', ['2026-10-03'], '25.0€');
        $otherLegacyId = $this->row('21269506', ['2026-10-10'], '25.0€');
        // Another source, whatever the length of its ids
        $openAgendaId = $this->row('oa-1', ['2026-10-03'], null, 'openagenda', sha1('workshop'));
        $otherOpenAgendaId = $this->row(sha1('oa-2'), ['2026-10-10'], null, 'openagenda', sha1('workshop'));
        $this->resolveFamilies([$legacyId, $otherLegacyId, $openAgendaId, $otherOpenAgendaId]);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($legacyId, $this->reload($otherLegacyId)->getDuplicateOf()?->getId());
        self::assertSame(self::HASH, $this->reload($otherLegacyId)->getIdentityHash());
        self::assertSame($openAgendaId, $this->reload($otherOpenAgendaId)->getDuplicateOf()?->getId());
        self::assertSame(sha1('workshop'), $this->reload($openAgendaId)->getIdentityHash());
    }

    public function testPreviewWritesNothing(): void
    {
        $legacyId = $this->row('21269507', ['2026-10-03'], '25.0€');
        $currentId = $this->row(sha1('Les Justes|place'), ['2026-10-10'], '27€');
        $this->resolveFamilies([$legacyId, $currentId]);

        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('1 show(s) would be handed to their current row', $display);
        self::assertSame($legacyId, $this->reload($currentId)->getDuplicateOf()?->getId());
        self::assertSame(self::HASH, $this->reload($legacyId)->getIdentityHash());
    }

    /**
     * A row with a timesheet of its own per date.
     *
     * @param non-empty-list<string> $dates
     */
    private function row(string $externalId, array $dates, ?string $prices, string $origin = 'awin.fnac', string $identityHash = self::HASH): int
    {
        $event = EventFactory::createOne([
            'externalId' => $externalId,
            'externalOrigin' => $origin,
            'identityHash' => $identityHash,
            'prices' => $prices,
            'startDate' => new DateTimeImmutable($dates[0]),
            'endDate' => new DateTimeImmutable($dates[array_key_last($dates)]),
        ]);
        foreach ($dates as $date) {
            EventTimesheetFactory::new()->on($date)->create(['event' => $event]);
        }

        return (int) $event->getId();
    }

    /**
     * The families as the identity backfill left them.
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
     * The event's timesheet dates, each with the id of the sibling it was inherited from
     * (null for the event's own rows).
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

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:retire-legacy-fnac-rows'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
