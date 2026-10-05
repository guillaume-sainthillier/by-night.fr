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
use App\Reject\Reject;
use App\Repository\EventRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
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
 * One-off deletion of the events whose place has no country, once app:places:locate-countryless
 * has located what it could: the ones left are abroad (Madrid, PortAventura, the Instituts
 * français) or have neither coordinates nor a postal code. No city or country page lists them,
 * they answered on "/unknown", and the firewall now turns such venues away (BAD_COUNTRY).
 *
 * They are removed through the ORM, so their search documents, pictures, cached pages and the
 * rows of their family go with them. An event a duplicate in a country redirects to is kept, or
 * the cascade would take that duplicate too. Their explorations get BAD_COUNTRY instead of the
 * EVENT_DELETED the removal records: they were not deleted by their creator, and a source that
 * corrects one has it judged again. The places left empty go with app:places:remove-eventless.
 * Previews by default, writes with --apply.
 */
#[AsCommand('app:events:remove-countryless', 'Delete the events whose place has no country (preview by default, --apply to write)')]
final class EventsRemoveCountrylessCommand extends Command
{
    private const int BATCH_SIZE = 100;

    private const int PREVIEW_SAMPLE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the deletions. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $kept = (int) $this
            ->createCountrylessQueryBuilder()
            ->select('COUNT(e.id)')
            ->andWhere(\sprintf('EXISTS (SELECT 1 FROM %s d JOIN d.place dp WHERE d.duplicateOf = e AND dp.country IS NOT NULL)', Event::class))
            ->getQuery()
            ->getSingleScalarResult();

        $events = new CursorPagination(
            $this->createRemovableQueryBuilder(),
            new OrderConfigurations(new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId())),
            self::BATCH_SIZE,
            fetchJoinCollection: false,
        );
        $total = \count($events);
        $io->section(\sprintf('%s %d event(s) whose place has no country', $apply ? 'Deleting' : 'Previewing', $total));
        $this->showSample($io);

        $removed = 0;
        $io->progressStart($total);
        foreach ($events->getChunkResults() as $chunk) {
            if ($apply) {
                $removed += $this->remove($chunk);
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        if ($kept > 0) {
            $io->text(\sprintf('%d event(s) kept: a duplicate in a country redirects to them.', $kept));
        }

        if ($apply) {
            $io->success(\sprintf('%d event(s) removed, with the rows of their families. Run app:places:remove-eventless --apply for their places.', $removed));
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to delete.');
        }

        return Command::SUCCESS;
    }

    private function createCountrylessQueryBuilder(): QueryBuilder
    {
        return $this
            ->eventRepository
            ->createQueryBuilder('e')
            ->join('e.place', 'p')
            ->where('p.country IS NULL');
    }

    private function createRemovableQueryBuilder(): QueryBuilder
    {
        return $this
            ->createCountrylessQueryBuilder()
            ->andWhere(\sprintf('NOT EXISTS (SELECT 1 FROM %s d JOIN d.place dp WHERE d.duplicateOf = e AND dp.country IS NOT NULL)', Event::class));
    }

    private function showSample(SymfonyStyle $io): void
    {
        /** @var list<array{id: int, name: string|null, place: string|null, town: string|null, endDate: DateTimeInterface|null}> $rows */
        $rows = $this
            ->createRemovableQueryBuilder()
            ->select('e.id', 'e.name', 'p.name AS place', 'p.cityName AS town', 'e.endDate')
            ->orderBy('e.endDate', 'DESC')
            ->setMaxResults(self::PREVIEW_SAMPLE)
            ->getQuery()
            ->getArrayResult();

        $io->table(['Event', 'Name', 'Place', 'Town', 'Ends'], array_map(
            static fn (array $row): array => [$row['id'], $row['name'], $row['place'], $row['town'], $row['endDate']?->format('Y-m-d')],
            $rows,
        ));
    }

    /**
     * @param Event[] $events
     *
     * @return int the events removed, the duplicates the cascade takes included
     */
    private function remove(array $events): int
    {
        foreach ($events as $event) {
            $this->entityManager->remove($event);
        }

        // The cascade schedules the rows of the families at remove() already
        $externalIds = [];
        $removed = 0;
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof Event) {
                continue;
            }

            ++$removed;
            if (null !== $entity->getExternalOrigin() && null !== $entity->getExternalId()) {
                $externalIds[$entity->getExternalOrigin()][] = $entity->getExternalId();
            }
        }

        $this->entityManager->flush();

        foreach ($externalIds as $origin => $ids) {
            $this->entityManager
                ->createQueryBuilder()
                ->update(ParserData::class, 'pd')
                ->set('pd.reason', ':reason')
                ->where('pd.externalOrigin = :origin')
                ->andWhere('pd.externalId IN (:ids)')
                ->andWhere('pd.reason = :deleted')
                ->setParameter('reason', new Reject()->addReason(Reject::BAD_COUNTRY)->getReason())
                ->setParameter('origin', $origin)
                // Strings: numeric ids bound as integers make MySQL compare numerically
                ->setParameter('ids', array_map(strval(...), $ids))
                ->setParameter('deleted', Reject::EVENT_DELETED)
                ->getQuery()
                ->execute();
        }

        return $removed;
    }
}
