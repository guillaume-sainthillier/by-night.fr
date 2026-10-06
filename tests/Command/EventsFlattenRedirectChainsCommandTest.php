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
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\Persistence\save;

/**
 * A duplicate pointing at another duplicate redirects twice and lends its dates to no canonical: it is pointed at the
 * canonical its chain ends on.
 */
final class EventsFlattenRedirectChainsCommandTest extends AppKernelTestCase
{
    private const string HASH = 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3';

    private Place $place;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        // One venue for every row, so Country's app-assigned PK is created once
        $this->place = PlaceFactory::createOne();
    }

    public function testADuplicateOfADuplicateIsPointedAtTheCanonical(): void
    {
        $canonicalId = $this->row('2030-10-03', self::HASH);
        $memberId = $this->row('2030-10-10', self::HASH, $canonicalId);
        // A link from before the identity hash, onto the row that later joined the family
        $stubId = $this->row('2019-11-24', null, $memberId);
        // Deeper still
        $deeperId = $this->row('2019-11-25', null, $stubId);

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        self::assertSame($canonicalId, $this->reload($stubId)->getDuplicateOf()?->getId());
        self::assertSame($canonicalId, $this->reload($deeperId)->getDuplicateOf()?->getId());
        self::assertSame($canonicalId, $this->reload($memberId)->getDuplicateOf()?->getId());
        self::assertSame(['2030-10-03' => null, '2030-10-10' => $memberId], $this->datesBySource($this->reload($canonicalId)), 'A row without identity lends no date.');
        self::assertStringContainsString('2 duplicate(s) pointed at their canonical (0 losing an identity hash of their own), 0 left alone', $display);
    }

    public function testARowOfTheFamilyLendsItsDatesOnceItReachesTheCanonical(): void
    {
        $canonicalId = $this->row('2030-10-03', self::HASH);
        $middleId = $this->row('2030-10-10', null, $canonicalId);
        $rowId = $this->row('2030-10-17', self::HASH, $middleId);

        $this->doRunCommand(['--apply' => true]);

        self::assertSame($canonicalId, $this->reload($rowId)->getDuplicateOf()?->getId());
        self::assertSame(self::HASH, $this->reload($rowId)->getIdentityHash());
        self::assertSame(['2030-10-03' => null, '2030-10-17' => $rowId], $this->datesBySource($this->reload($canonicalId)));
    }

    public function testARowOfAnotherIdentityStaysARedirect(): void
    {
        // As app:events:retire-legacy-fnac-rows left them: a legacy row under the current one, its own links under it
        $canonicalId = $this->row('2030-10-03', self::HASH);
        $middleId = $this->row('2026-04-11', null, $canonicalId);
        $rowId = $this->row('2026-04-12', sha1('another identity'), $middleId);

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        $row = $this->reload($rowId);
        self::assertSame($canonicalId, $row->getDuplicateOf()?->getId(), 'Not set free as another event.');
        self::assertNull($row->getIdentityHash());
        self::assertSame(['2030-10-03' => null], $this->datesBySource($this->reload($canonicalId)));
        self::assertStringContainsString('1 duplicate(s) pointed at their canonical (1 losing an identity hash of their own)', $display);
    }

    public function testALoopIsLeftAlone(): void
    {
        $firstId = $this->row('2030-10-03', null);
        $secondId = $this->row('2030-10-10', null, $firstId);
        save($this->reload($firstId)->setDuplicateOf($this->reload($secondId)));

        $display = self::unwrap($this->doRunCommand(['--apply' => true])->getDisplay());

        self::assertSame($secondId, $this->reload($firstId)->getDuplicateOf()?->getId());
        self::assertSame($firstId, $this->reload($secondId)->getDuplicateOf()?->getId());
        self::assertStringContainsString('0 duplicate(s) pointed at their canonical (0 losing an identity hash of their own), 2 left alone: their chain loops', $display);
    }

    public function testPreviewWritesNothing(): void
    {
        $canonicalId = $this->row('2030-10-03', self::HASH);
        $memberId = $this->row('2030-10-10', self::HASH, $canonicalId);
        $stubId = $this->row('2019-11-24', sha1('another identity'), $memberId);

        $display = self::unwrap($this->doRunCommand([])->getDisplay());

        self::assertStringContainsString('1 duplicate(s) would be pointed at their canonical (1 losing an identity hash of their own)', $display);
        self::assertSame($memberId, $this->reload($stubId)->getDuplicateOf()?->getId());
        self::assertSame(sha1('another identity'), $this->reload($stubId)->getIdentityHash());
    }

    /**
     * A row with a timesheet on its date, pointing at $duplicateOfId when given.
     */
    private function row(string $date, ?string $identityHash, ?int $duplicateOfId = null): int
    {
        $event = EventFactory::createOne([
            'externalOrigin' => 'datatourisme',
            'identityHash' => $identityHash,
            'name' => 'Visite guidée',
            'place' => $this->place,
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($date),
            'duplicateOf' => null === $duplicateOfId ? null : $this->reload($duplicateOfId),
        ]);
        EventTimesheetFactory::new()->on($date)->create(['event' => $event]);

        return (int) $event->getId();
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }

    /**
     * The event's timesheet dates, each with the id of the sibling it was inherited from (null for its own).
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
        $tester = new CommandTester(new Application(self::$kernel)->find('app:events:flatten-redirect-chains'));
        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester;
    }
}
