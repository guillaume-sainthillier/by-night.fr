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
use App\Import\EventFamilyResolver;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Points every duplicate at the canonical at the end of its chain.
 *
 * A duplicate redirects to the row it points at, in one hop: pointing at another duplicate, it redirects twice, and
 * lends its dates to no canonical. The family backfill left such chains when a row joined a family with the links
 * made onto it (557 DataTourisme rows among 594 on 2026-10-06), and so did app:events:retire-legacy-fnac-rows. A row
 * whose identity hash differs from its canonical's loses it, like the links that predate the hash: it stays a
 * redirect instead of being set free as another event by the canonical's next import. Previews by default, writes
 * with --apply.
 */
#[AsCommand('app:events:flatten-redirect-chains', 'Point every duplicate at the canonical at the end of its chain (preview by default, --apply to write)')]
final class EventsFlattenRedirectChainsCommand extends Command
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventFamilyResolver $familyResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $ids = $this->findChainedIds();
        $io->section(\sprintf('%s %d duplicate(s) pointing at another duplicate', $apply ? 'Flattening' : 'Previewing', \count($ids)));

        $flattened = 0;
        $unhashed = 0;
        $cycles = 0;
        $sample = [];
        $io->progressStart(\count($ids));
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $canonicalIds = [];
            foreach ($this->eventRepository->findBy(['id' => $chunk]) as $row) {
                $via = $row->getDuplicateOf();
                $canonical = null === $via ? null : self::endOfChain($via);
                if (null === $via || null === $canonical) {
                    ++$cycles;

                    continue;
                }

                if (\count($sample) < 10) {
                    $sample[] = [$row->getId(), $row->getName(), $via->getId(), $canonical->getId()];
                }

                $row->setDuplicateOf($canonical);
                if (null !== $row->getIdentityHash() && $row->getIdentityHash() !== $canonical->getIdentityHash()) {
                    $row->setIdentityHash(null);
                    ++$unhashed;
                }

                $canonicalIds[] = (int) $canonical->getId();
                ++$flattened;
            }

            if ($apply) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // The canonicals inherit the dates of the rows of their family that reach them now
                $this->familyResolver->resolveForEvents($canonicalIds);
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Id', 'Event', 'Pointed at', 'Canonical'], $sample);

        $summary = \sprintf(
            '%d duplicate(s) %s at their canonical (%d losing an identity hash of their own), %d left alone: their chain loops.',
            $flattened,
            $apply ? 'pointed' : 'would be pointed',
            $unhashed,
            $cycles,
        );

        if ($apply) {
            $io->success($summary);
        } else {
            $io->note($summary . ' Preview only, nothing was written: re-run with --apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * The duplicates pointing at another duplicate.
     *
     * @return list<int>
     */
    private function findChainedIds(): array
    {
        return array_map(intval(...), $this->eventRepository
            ->createQueryBuilder('e')
            ->select('e.id')
            ->join('e.duplicateOf', 'd')
            ->where('d.duplicateOf IS NOT NULL')
            ->orderBy('e.id', SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * The canonical a chain ends on, or null when it loops.
     */
    private static function endOfChain(Event $event): ?Event
    {
        $seen = [];
        while (null !== $event->getDuplicateOf()) {
            if (isset($seen[$event->getId()])) {
                return null;
            }

            $seen[$event->getId()] = true;
            $event = $event->getDuplicateOf();
        }

        return $event;
    }
}
