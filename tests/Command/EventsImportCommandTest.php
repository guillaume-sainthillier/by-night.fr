<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\EventsImportCommand;
use App\Repository\ParserStateRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;
use Psr\Log\NullLogger;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command hands every parser the start of its previous successful run, so an
 * incremental import fetches exactly what changed since then — however many nights the
 * cron missed — and records the start of the run it just completed.
 */
final class EventsImportCommandTest extends AppKernelTestCase
{
    private ParserStateRepository $parserStates;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parserStates = self::getContainer()->get(ParserStateRepository::class);
    }

    public function testTheFirstRunIsAFullImportAndRecordsWhenItStarted(): void
    {
        $parser = new RecordingParser('fake');
        $before = new DateTimeImmutable();

        $this->import([$parser], 'fake');

        self::assertSame([null], $parser->runs, 'Nothing to start from: full import');
        $lastParsedAt = $this->parserStates->findLastParsedAt('fake');
        self::assertNotNull($lastParsedAt);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $lastParsedAt->getTimestamp());
        self::assertLessThanOrEqual(new DateTimeImmutable()->getTimestamp(), $lastParsedAt->getTimestamp());
    }

    public function testTheNextRunStartsFromWhereThePreviousOneStarted(): void
    {
        $previousRun = new DateTimeImmutable('2026-09-21 02:00:00');
        $this->parserStates->markParsed('fake', $previousRun);
        $parser = new RecordingParser('fake');

        $this->import([$parser], 'fake');

        self::assertCount(1, $parser->runs);
        self::assertSame('2026-09-21 02:00:00', $parser->runs[0]?->format('Y-m-d H:i:s'));
        self::assertGreaterThan($previousRun, $this->parserStates->findLastParsedAt('fake'), 'The watermark moved to this run');
    }

    public function testFullOptionIgnoresTheWatermarkButStillMovesIt(): void
    {
        $previousRun = new DateTimeImmutable('2026-09-21 02:00:00');
        $this->parserStates->markParsed('fake', $previousRun);
        $parser = new RecordingParser('fake');

        $this->import([$parser], 'fake', ['--full' => true]);

        self::assertSame([null], $parser->runs);
        self::assertGreaterThan($previousRun, $this->parserStates->findLastParsedAt('fake'));
    }

    public function testAFailedRunDoesNotMoveTheWatermark(): void
    {
        $previousRun = new DateTimeImmutable('2026-09-21 02:00:00');
        $this->parserStates->markParsed('fake', $previousRun);
        $parser = new RecordingParser('fake', failing: true);

        try {
            $this->import([$parser], 'fake');
            self::fail('The parser failure must surface');
        } catch (RuntimeException) {
        }

        self::assertSame([$previousRun->format('U')], [$this->parserStates->findLastParsedAt('fake')?->format('U')], 'The next run re-fetches from the previous one');
    }

    public function testAFailingParserDoesNotStopTheOthersOfAFullRun(): void
    {
        $failing = new RecordingParser('failing', failing: true);
        $next = new RecordingParser('next');

        $tester = new CommandTester(new EventsImportCommand([$failing, $next], $this->parserStates, new NullLogger()));
        $status = $tester->execute(['parser' => 'all']);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([null], $next->runs, 'The parser after the failing one still ran');
        self::assertNotNull($this->parserStates->findLastParsedAt('next'));
        self::assertNull($this->parserStates->findLastParsedAt('failing'));
    }

    public function testEveryEnabledParserHasItsOwnWatermarkAndDisabledOnesAreSkipped(): void
    {
        $this->parserStates->markParsed('first', new DateTimeImmutable('2026-09-20 02:00:00'));
        $first = new RecordingParser('first');
        $second = new RecordingParser('second');
        $disabled = new RecordingParser('disabled', enabled: false);

        $this->import([$first, $second, $disabled], 'all');

        self::assertSame('2026-09-20 02:00:00', $first->runs[0]?->format('Y-m-d H:i:s'));
        self::assertSame([null], $second->runs, 'Never ran before: full import');
        self::assertSame([], $disabled->runs);
        self::assertNotNull($this->parserStates->findLastParsedAt('second'));
        self::assertNull($this->parserStates->findLastParsedAt('disabled'));
    }

    /**
     * @param list<RecordingParser> $parsers
     * @param array<string, mixed>  $options
     */
    private function import(array $parsers, string $parserName, array $options = []): void
    {
        $tester = new CommandTester(new EventsImportCommand($parsers, $this->parserStates, new NullLogger()));
        $tester->execute(['parser' => $parserName, ...$options]);
    }
}
