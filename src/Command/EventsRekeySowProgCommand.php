<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Event;
use App\Entity\ParserData;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use SplFileObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Hands the SowProg events of the v1.2 feed over to the ids of the API v2.
 *
 * The v1.2 feed served one "programme" per show, with all its dates; the API v2 serves one event
 * per date, under new ids. Without this, the first v2 import would create every upcoming show a
 * second time. Sowprog sent the mapping as a CSV, one line per v2 event (ID_V2, ID_LEGACY_PROGRAMME,
 * ID_LEGACY_EVENEMENT, ID_LEGACY_DATE, DATE, STATUT, TITRE). Each stored row takes one v2 id, the
 * most exact first:
 *
 * 1. a row of the per-date scheme, "<programme>-<date id>" (stored until 2026-01), the v2 id of that date;
 * 2. a row stored under its programme id, the v2 id of the programme's next date (else of its last);
 * 3. a row still to come that the CSV does not know (a programme created after Sowprog's export), the
 *    v2 event of the live feed with the same title on one of its days, when there is exactly one.
 *
 * The next import then updates each row instead of creating it: it keeps its page, comments and
 * participants, and as the oldest row it heads the family the v2 events of its other dates join
 * (EventFamilyResolver). A v2 id already stored (by an import run before this command) is left
 * alone, and so is the row that wanted it. The SowProg ParserData rows are dropped: the content
 * hashes of the old ids are of no use to the new ones.
 *
 * Previews by default, writes with --apply.
 */
#[AsCommand('app:events:rekey-sowprog', 'Move the SowProg events from their v1.2 ids to the API v2 ids (preview by default, --apply to write)')]
final class EventsRekeySowProgCommand extends Command
{
    private const string ORIGIN = 'sowprog';

    /**
     * Statuses the API serves: a DRAFT is never imported, so a row given its id would never be updated.
     */
    private const array SERVED_STATUSES = ['PUBLISHED', 'CANCELLED', 'POSTPONED'];

    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Target('sowprog.client')]
        private readonly HttpClientInterface $sowprogClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv', InputArgument::REQUIRED, 'The v1 → v2 mapping sent by Sowprog')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $today = new DateTimeImmutable('today')->format('Y-m-d');

        [$byProgramme, $byDate] = $this->readMapping((string) $input->getArgument('csv'));
        $rows = $this->findLegacyRows();
        $io->section(\sprintf('%s %d SowProg event(s) stored under a v1.2 id', $apply ? 'Re-keying' : 'Previewing', \count($rows)));

        /** @var array<string, string> $newIds v2 id by stored external id */
        $newIds = [];
        /** @var array<string, true> $claimed v2 ids given to a row */
        $claimed = [];
        $claim = static function (string $externalId, string $v2Id) use (&$newIds, &$claimed): void {
            $newIds[$externalId] = $v2Id;
            $claimed[$v2Id] = true;
        };

        // 1. The per-date rows: exact
        foreach ($rows as $externalId => $row) {
            if (null !== $row['dateId'] && isset($byDate[$row['dateId']]) && !isset($claimed[$byDate[$row['dateId']]])) {
                $claim($externalId, $byDate[$row['dateId']]);
            }
        }
        $byDateCount = \count($newIds);

        // 2. The programme rows: their next date nobody took
        foreach ($rows as $externalId => $row) {
            if (null === $row['dateId'] && isset($byProgramme[$row['programmeId']])) {
                $v2Id = self::pick(array_diff_key($byProgramme[$row['programmeId']], $claimed), $today);
                if (null !== $v2Id) {
                    $claim($externalId, $v2Id);
                }
            }
        }
        $byProgrammeCount = \count($newIds) - $byDateCount;

        // 3. The rows still to come the CSV does not know: the live feed, by title and day
        $unknown = array_filter($rows, static fn (array $row, string $externalId): bool => !isset($newIds[$externalId]) && $row['endDate'] >= $today, \ARRAY_FILTER_USE_BOTH);
        if ([] !== $unknown) {
            $io->text(\sprintf('%d upcoming event(s) missing from the CSV: reading the live feed', \count($unknown)));
            $feed = $this->readFeed();
            foreach ($unknown as $externalId => $row) {
                $candidates = array_diff_key($feed[self::normalize($row['name'])] ?? [], $claimed);
                $candidates = array_filter($candidates, static fn (string $date): bool => $date >= $row['startDate'] && $date <= $row['endDate']);
                // Only on the row's own days: a title alone ("Jam session") would match other shows. The
                // programme's dates are several v2 events of one title, its next one is taken first.
                $v2Id = self::pick($candidates, $today);
                if (null !== $v2Id) {
                    $claim($externalId, $v2Id);
                }
            }
        }
        $byFeedCount = \count($newIds) - $byDateCount - $byProgrammeCount;

        // A v2 id an earlier import already stored stays with its row
        $taken = array_flip($this->findStoredIds(array_values($newIds)));
        $skipped = array_filter($newIds, static fn (string $v2Id): bool => isset($taken[$v2Id]));
        $newIds = array_diff_key($newIds, $skipped);

        $upcomingLeft = \count(array_filter($rows, static fn (array $row, string $externalId): bool => !isset($newIds[$externalId]) && $row['endDate'] >= $today, \ARRAY_FILTER_USE_BOTH));
        $io->table(['', 'Event(s)'], [
            ['Per-date rows, by their date id', $byDateCount],
            ['Programme rows, by their next date', $byProgrammeCount],
            ['Missing from the CSV, by title and day in the live feed', $byFeedCount],
            ['Left alone: their v2 id is already stored', \count($skipped)],
            ['Still to come and not re-keyed', $upcomingLeft],
        ]);

        if (!$apply) {
            $io->note(\sprintf('%d event(s) would be re-keyed. Preview only, nothing was written: re-run with --apply.', \count($newIds)));

            return Command::SUCCESS;
        }

        $this->entityManager->wrapInTransaction(function () use ($newIds): void {
            $query = $this->entityManager->createQuery(\sprintf(
                'UPDATE %s e SET e.externalId = :new WHERE e.externalOrigin = :origin AND e.externalId = :old',
                Event::class,
            ));
            foreach ($newIds as $externalId => $v2Id) {
                $query->execute(['new' => $v2Id, 'old' => (string) $externalId, 'origin' => self::ORIGIN]);
            }

            $this->entityManager
                ->createQuery(\sprintf('DELETE FROM %s p WHERE p.externalOrigin = :origin', ParserData::class))
                ->execute(['origin' => self::ORIGIN]);
        });

        $io->success(\sprintf('%d event(s) re-keyed to their API v2 id. Run "app:events:import sowprog --full" next.', \count($newIds)));

        return Command::SUCCESS;
    }

    /**
     * The v2 id a row takes among the dates left: the next one, else the last one.
     *
     * @param array<int|string, string> $dates v2 id => Y-m-d
     */
    private static function pick(array $dates, string $today): ?string
    {
        if ([] === $dates) {
            return null;
        }

        asort($dates);
        $upcoming = array_filter($dates, static fn (string $date): bool => $date >= $today);

        return (string) ([] !== $upcoming ? array_key_first($upcoming) : array_key_last($dates));
    }

    /**
     * The served v2 events of the CSV, by programme and by date id. PHP turns the numeric keys into
     * integers: they are compared as such, and bound back as strings by findStoredIds().
     *
     * @return array{array<int|string, non-empty-array<int|string, string>>, array<int|string, string>} [programme id => [v2 id => Y-m-d]], [date id => v2 id]
     */
    private function readMapping(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $byProgramme = [];
        $byDate = [];
        foreach ($file as $row) {
            /* @var list<string|null> $row */
            if (null === $header) {
                $header = array_flip(array_map(static fn (?string $column): string => trim((string) $column), $row));

                continue;
            }

            if (!\in_array($row[$header['STATUT']], self::SERVED_STATUSES, true)) {
                continue;
            }

            $v2Id = (string) $row[$header['ID_V2']];
            $byProgramme[$row[$header['ID_LEGACY_PROGRAMME']]][$v2Id] = (string) $row[$header['DATE']];
            $byDate[$row[$header['ID_LEGACY_DATE']]] = $v2Id;
        }

        return [$byProgramme, $byDate];
    }

    /**
     * The SowProg events still under a v1.2 id: "<programme>" or "<programme>-<date id>". The v2
     * ids are far below the programme ids (8,115 vs 320,651 and up on 2026-10-05).
     *
     * @return array<string, array{programmeId: string, dateId: string|null, name: string, startDate: string, endDate: string}>
     */
    private function findLegacyRows(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.externalId', 'e.name', 'e.startDate', 'e.endDate')
            ->from(Event::class, 'e')
            ->where('e.externalOrigin = :origin')
            ->setParameter('origin', self::ORIGIN)
            ->getQuery()
            ->getArrayResult();

        $legacy = [];
        foreach ($rows as $row) {
            if (1 !== preg_match('/^(\d{6,})(?:-(\d+))?$/', (string) $row['externalId'], $matches)) {
                continue;
            }

            $legacy[(string) $row['externalId']] = [
                'programmeId' => $matches[1],
                'dateId' => $matches[2] ?? null,
                'name' => (string) $row['name'],
                'startDate' => self::day($row['startDate']),
                'endDate' => self::day($row['endDate'] ?? $row['startDate']),
            ];
        }

        return $legacy;
    }

    /**
     * The events of the live feed, by normalized title.
     *
     * @return array<string, array<int|string, string>> title => [v2 id => Y-m-d]
     */
    private function readFeed(): array
    {
        $feed = [];
        $page = 1;
        do {
            $data = $this->sowprogClient->request('GET', 'events', ['query' => ['page' => $page, 'pageSize' => 200, 'modifiedSince' => 0]])->toArray();
            foreach ($data['data'] ?? [] as $event) {
                foreach ($event['dates'] ?? [] as $date) {
                    $feed[self::normalize(html_entity_decode((string) $event['title'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))][(string) $event['id']] = substr((string) $date['date'], 0, 10);
                }
            }
        } while ($page++ < ($data['meta']['totalPages'] ?? 0));

        return $feed;
    }

    private static function normalize(string $title): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($title)));
    }

    private static function day(mixed $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }

    /**
     * Among the given external ids, the ones stored as SowProg events.
     *
     * @param list<string> $externalIds
     *
     * @return list<string>
     */
    private function findStoredIds(array $externalIds): array
    {
        $stored = [];
        foreach (array_chunk($externalIds, self::BATCH_SIZE) as $chunk) {
            $rows = $this->entityManager->createQueryBuilder()
                ->select('e.externalId')
                ->from(Event::class, 'e')
                ->where('e.externalOrigin = :origin')
                ->andWhere('e.externalId IN (:ids)')
                ->setParameter('origin', self::ORIGIN)
                // Strings: numeric ids bound as integers make MySQL cast every external_id
                ->setParameter('ids', array_map(strval(...), $chunk), ArrayParameterType::STRING)
                ->getQuery()
                ->getSingleColumnResult();

            array_push($stored, ...array_map(strval(...), $rows));
        }

        return $stored;
    }
}
