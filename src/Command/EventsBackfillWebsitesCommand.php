<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Import\Cleaner;
use App\Repository\EventRepository;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:events:backfill-websites',
    description: 'Clean the websites of existing events like imports do: split the multi-URL values, drop what cannot be linked',
)]
final class EventsBackfillWebsitesCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 200;

    private const int SCAN_SIZE = 5_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly Cleaner $cleaner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be cleaned without writing anything (list it with -v)')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Events loaded per flush', (string) self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Backfilling event websites');

        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        // Only a few thousand events out of a million need cleaning: find them on the raw column
        // instead of hydrating every event.
        $total = (int) $this->eventRepository
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.websiteContacts IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var array<int, array{before: string[], after: string[]|null}> $changes */
        $changes = [];
        $io->text(\sprintf('Scanning the websites of %d event(s)', $total));
        Monitor::createProgressBar($total);
        foreach ($this->scan() as $id => $websites) {
            $cleaned = $this->cleaner->cleanWebsites($websites) ?: null;
            if ($cleaned !== $websites) {
                $changes[$id] = ['before' => $websites, 'after' => $cleaned];
            }

            Monitor::advanceProgressBar();
        }

        Monitor::finishProgressBar();
        $io->newLine(2);

        if ([] !== $changes && $io->isVerbose()) {
            $io->listing(array_map(
                static fn (int $id, array $change): string => \sprintf('#%d %s => %s', $id, json_encode($change['before'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), json_encode($change['after'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)),
                array_keys($changes),
                $changes,
            ));
        }

        $emptied = \count(array_filter($changes, static fn (array $change): bool => null === $change['after']));
        $io->table(['Scanned', 'To clean', 'Left without a website'], [[$total, \count($changes), $emptied]]);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $this->apply($changes, $batchSize);
        $io->success(\sprintf('%d event(s) cleaned.', \count($changes)));

        return Command::SUCCESS;
    }

    /**
     * Keyset pagination on the id, over the id and websites only.
     *
     * @return Generator<int, string[]>
     */
    private function scan(): Generator
    {
        $lastId = 0;
        do {
            /** @var array{id: int, websiteContacts: string[]}[] $rows */
            $rows = $this->eventRepository
                ->createQueryBuilder('e')
                ->select('e.id', 'e.websiteContacts')
                ->where('e.websiteContacts IS NOT NULL')
                ->andWhere('e.id > :lastId')
                ->setParameter('lastId', $lastId)
                ->orderBy('e.id', 'ASC')
                ->setMaxResults(self::SCAN_SIZE)
                ->getQuery()
                ->getArrayResult();

            foreach ($rows as ['id' => $lastId, 'websiteContacts' => $websites]) {
                yield $lastId => $websites;
            }
        } while (self::SCAN_SIZE === \count($rows));
    }

    /**
     * @param array<int, array{before: string[], after: string[]|null}> $changes
     */
    private function apply(array $changes, int $batchSize): void
    {
        Monitor::createProgressBar(\count($changes));
        foreach (array_chunk($changes, $batchSize, true) as $chunk) {
            foreach ($this->eventRepository->findBy(['id' => array_keys($chunk)]) as $event) {
                // Websites are not part of the indexed document: don't reindex the event
                $event->batchUpdate = true;
                $event->setWebsiteContacts($chunk[$event->getId()]['after']);
                Monitor::advanceProgressBar();
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        Monitor::finishProgressBar();
    }
}
