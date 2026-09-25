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
use App\Entity\EventTimesheet;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives the events stored with their end before their start the range of their sessions.
 *
 * The DataTourisme parser took the first and last periods of a feed record as they came, not
 * sorted: 11,485 events of production end before they start, which the listings filtering on
 * the range (city home, widgets) never show. Their sessions are right: the range becomes the
 * one of their first and last sessions, and the flush re-indexes them. Previews by default,
 * writes with --apply.
 */
#[AsCommand('app:events:fix-inverted-dates', 'Give the events ending before they start the range of their sessions (preview by default, --apply to write)')]
final class EventsFixInvertedDatesCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the dates. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        // Walked by id, from the last event of the previous chunk: a fixed event leaves the query
        $pagination = new CursorPagination(
            $this->eventRepository->createQueryBuilder('e')->where('e.endDate < e.startDate'),
            new OrderConfigurations(new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId())),
            self::BATCH_SIZE,
            fetchJoinCollection: false,
        );
        $total = \count($pagination);
        $io->section(\sprintf('%s %d event(s) ending before they start', $apply ? 'Fixing' : 'Previewing', $total));

        $fixed = 0;
        $sample = [];
        $io->progressStart($total);
        foreach ($pagination->getChunkResults() as $events) {
            $ranges = $this->findSessionRanges($events);
            foreach ($events as $event) {
                $range = $ranges[$event->getId()] ?? null;
                if (null === $range) {
                    continue;
                }

                if (\count($sample) < 10) {
                    $sample[] = [$event->getId(), $event->getName(), \sprintf('%s → %s', $event->getStartDate()?->format('Y-m-d'), $event->getEndDate()?->format('Y-m-d')), \sprintf('%s → %s', $range[0]->format('Y-m-d'), $range[1]->format('Y-m-d'))];
                }

                $event->setStartDate($range[0]);
                $event->setEndDate($range[1]);
                ++$fixed;
            }

            if ($apply) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($events));
        }

        $io->progressFinish();
        $io->table(['Id', 'Event', 'Stored', 'Its sessions'], $sample);

        if ($apply) {
            $io->success(\sprintf('%d event(s) fixed.', $fixed));
        } else {
            $io->note(\sprintf('%d event(s) would be fixed. Preview only, nothing was written: re-run with --apply.', $fixed));
        }

        return Command::SUCCESS;
    }

    /**
     * The first and last day of the sessions of each event, in one query.
     *
     * @param Event[] $events
     *
     * @return array<int, array{DateTimeImmutable, DateTimeImmutable}>
     */
    private function findSessionRanges(array $events): array
    {
        /** @var list<array{id: int, firstDay: string, lastDay: string}> $rows */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('IDENTITY(t.event) AS id, MIN(t.startAt) AS firstDay, MAX(t.endAt) AS lastDay')
            ->from(EventTimesheet::class, 't')
            ->where('t.event IN (:events)')
            ->andWhere('t.startAt IS NOT NULL AND t.endAt IS NOT NULL')
            ->groupBy('t.event')
            ->setParameter('events', $events)
            ->getQuery()
            ->getArrayResult();

        $ranges = [];
        foreach ($rows as $row) {
            $ranges[(int) $row['id']] = [new DateTimeImmutable($row['firstDay'])->setTime(0, 0), new DateTimeImmutable($row['lastDay'])->setTime(0, 0)];
        }

        return $ranges;
    }
}
