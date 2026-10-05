<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Import\Cleaner;
use App\Utils\HoursLabel;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves the hours labels stored before the times into the times, as an import now does (Cleaner).
 *
 * 1. The timesheets: a label that only states a slot ("À 20h30", "De 10h00 à 18h00") becomes the times of the
 *    session and goes, the whole-day markers ("De 00h00 à 23h59", "À 00h00") go without times. A label saying more
 *    stays.
 * 2. The events: one without timesheets gets the times of its label the same way. One with timesheets gets the span
 *    of its sessions as its times, and loses its label when a slot: a parser summed up its sessions with it, each of
 *    them having it too, while the event form made it the default of the dates without hours of their own, which
 *    take it.
 *
 * Plain SQL by id ranges, as 6M rows go through it: nothing is re-indexed nor purged, the labels shown stay the same.
 * Previews by default, writes with --apply; running it again finds nothing left to do.
 */
#[AsCommand('app:events:backfill-times', 'Move the hours labels that only state a slot into the times of the sessions (preview by default, --apply to write)')]
final class EventsBackfillTimesCommand extends Command
{
    private const int BATCH_SIZE = 2000;

    private const int PREVIEW_SAMPLE = 15;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $sample = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly Cleaner $cleaner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the times. Without it the command only counts what it would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $io->section(\sprintf('%s the labels of the timesheets', $apply ? 'Moving' : 'Previewing'));
        $timesheets = $this->backfillTimesheets($io, $apply);
        $io->section(\sprintf('%s the times of the events', $apply ? 'Moving' : 'Previewing'));
        [$events, $defaults] = $this->backfillEvents($io, $apply);

        $io->table(['Row', 'Before', 'After'], $this->sample);
        $message = \sprintf('%d timesheet(s), %d event(s) and %d date(s) following a default slot', $timesheets, $events, $defaults);
        if ($apply) {
            $io->success($message . ' updated.');
        } else {
            $io->note($message . ' would change. Preview only, nothing was written: re-run with --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * Every timesheet with a label, cleaned as an import cleans it.
     */
    private function backfillTimesheets(SymfonyStyle $io, bool $apply): int
    {
        $changed = 0;
        $lastId = 0;
        $io->progressStart();
        do {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, start_time, end_time, hours FROM event_timesheet WHERE id > ? AND hours IS NOT NULL ORDER BY id LIMIT ' . self::BATCH_SIZE,
                [$lastId],
            );

            /** @var array<string, array{0: string|null, 1: string|null, 2: string|null, 3: list<int>}> $updates */
            $updates = [];
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $timesheet = new EventTimesheetDto();
                $timesheet->startTime = self::time($row['start_time']);
                $timesheet->endTime = self::time($row['end_time']);
                $timesheet->hours = $row['hours'];
                $this->cleaner->cleanEventTimesheet($timesheet);

                $after = [self::column($timesheet->startTime), self::column($timesheet->endTime), $timesheet->hours];
                if ($after === [$row['start_time'], $row['end_time'], $row['hours']]) {
                    continue;
                }

                $key = implode("\0", array_map(strval(...), $after));
                $updates[$key] ??= [...$after, []];
                $updates[$key][3][] = $lastId;
                ++$changed;
                $this->addSample('timesheet #' . $lastId, $row, $after);
            }

            if ($apply) {
                foreach ($updates as [$startTime, $endTime, $hours, $ids]) {
                    $this->connection->executeStatement(
                        'UPDATE event_timesheet SET start_time = ?, end_time = ?, hours = ? WHERE id IN (?)',
                        [$startTime, $endTime, $hours, $ids],
                        [3 => ArrayParameterType::INTEGER],
                    );
                }
            }

            $io->progressAdvance(\count($rows));
        } while (self::BATCH_SIZE === \count($rows));

        $io->progressFinish();

        return $changed;
    }

    /**
     * Every event with a label or timesheets, read after its timesheets.
     *
     * @return array{0: int, 1: int} the events changed, and the dates given their event's default slot
     */
    private function backfillEvents(SymfonyStyle $io, bool $apply): array
    {
        $changed = 0;
        $defaults = 0;
        $lastId = 0;
        $io->progressStart();
        do {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT e.id, e.start_time, e.end_time, e.hours, e.external_origin FROM `event` e
                WHERE e.id > ? AND (e.hours IS NOT NULL OR EXISTS (SELECT 1 FROM event_timesheet t WHERE t.event_id = e.id))
                ORDER BY e.id LIMIT ' . self::BATCH_SIZE,
                [$lastId],
            );
            if ([] === $rows) {
                break;
            }

            $lastId = (int) end($rows)['id'];
            $timesheets = $this->timesheets(array_map(static fn (array $row): int => (int) $row['id'], $rows));

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $event = new EventDto();
                $event->startTime = self::time($row['start_time']);
                $event->endTime = self::time($row['end_time']);
                $event->hours = $row['hours'];

                // The label of an event with dates: the default of a member's dates without hours, which take it
                $slot = [] === ($timesheets[$id] ?? []) ? null : HoursLabel::parse($event->hours);
                $isMembers = null === $row['external_origin'];
                $defaulted = [];
                foreach ($timesheets[$id] ?? [] as $timesheetId => $timesheet) {
                    if (null !== $slot && $isMembers && null === $timesheet->startTime && null === $timesheet->endTime && null === $timesheet->hours) {
                        [$timesheet->startTime, $timesheet->endTime] = $slot;
                        $defaulted[] = $timesheetId;
                    }

                    $event->timesheets[] = $timesheet;
                }

                if (null !== $slot) {
                    $event->hours = null;
                }

                $this->cleaner->cleanEventTimes($event);
                $after = [self::column($event->startTime), self::column($event->endTime), $event->hours];
                if ($after !== [$row['start_time'], $row['end_time'], $row['hours']]) {
                    ++$changed;
                    $this->addSample('event #' . $id, $row, $after);
                    if ($apply) {
                        $this->connection->executeStatement('UPDATE `event` SET start_time = ?, end_time = ?, hours = ? WHERE id = ?', [...$after, $id]);
                    }
                }

                if ([] !== $defaulted && null !== $slot) {
                    $defaults += \count($defaulted);
                    if ($apply) {
                        $this->connection->executeStatement(
                            'UPDATE event_timesheet SET start_time = ?, end_time = ? WHERE id IN (?)',
                            [self::column($slot[0]), self::column($slot[1]), $defaulted],
                            [2 => ArrayParameterType::INTEGER],
                        );
                    }
                }
            }

            $io->progressAdvance(\count($rows));
        } while (self::BATCH_SIZE === \count($rows));

        $io->progressFinish();

        return [$changed, $defaults];
    }

    /**
     * The timesheets of the events, by event then timesheet id. Read after the first pass: with their times, if
     * --apply wrote them; as an import would clean them otherwise.
     *
     * @param list<int> $eventIds
     *
     * @return array<int, array<int, EventTimesheetDto>>
     */
    private function timesheets(array $eventIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, event_id, start_at, end_at, start_time, end_time, hours FROM event_timesheet WHERE event_id IN (?)',
            [$eventIds],
            [ArrayParameterType::INTEGER],
        );

        $timesheets = [];
        foreach ($rows as $row) {
            $timesheet = new EventTimesheetDto();
            $timesheet->startAt = new DateTimeImmutable($row['start_at']);
            $timesheet->endAt = new DateTimeImmutable($row['end_at']);
            $timesheet->startTime = self::time($row['start_time']);
            $timesheet->endTime = self::time($row['end_time']);
            $timesheet->hours = $row['hours'];
            $this->cleaner->cleanEventTimesheet($timesheet);
            $timesheets[(int) $row['event_id']][(int) $row['id']] = $timesheet;
        }

        return $timesheets;
    }

    /**
     * @param array<string, mixed>                                  $before
     * @param array{0: string|null, 1: string|null, 2: string|null} $after
     */
    private function addSample(string $row, array $before, array $after): void
    {
        if (\count($this->sample) < self::PREVIEW_SAMPLE) {
            $this->sample[] = [$row, self::describe([$before['start_time'], $before['end_time'], $before['hours']]), self::describe($after)];
        }
    }

    /**
     * @param array{0: mixed, 1: mixed, 2: mixed} $values
     */
    private static function describe(array $values): string
    {
        return \sprintf('%s → %s | %s', $values[0] ?? '·', $values[1] ?? '·', $values[2] ?? '·');
    }

    private static function time(mixed $value): ?DateTimeImmutable
    {
        return \is_string($value) ? (DateTimeImmutable::createFromFormat('!H:i:s', $value) ?: null) : null;
    }

    /**
     * As the TIME columns hold it.
     */
    private static function column(?DateTimeInterface $time): ?string
    {
        return $time?->format('H:i:s');
    }
}
