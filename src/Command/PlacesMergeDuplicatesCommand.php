<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Place;
use App\Entity\PlaceMetadata;
use App\Repository\EventRepository;
use App\Repository\PlaceMetadataRepository;
use App\Repository\PlaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Makes each external identity (place_metadata external_id + external_origin) point
 * to a single place, which is what the unique key on that table requires.
 *
 * For every identity recorded on several places, the place carrying the most events
 * is kept (the oldest on a tie); the others hand over their events, the identities
 * and name slugs the keeper lacks, and are deleted. Identities recorded twice on the
 * same place are collapsed to one row. Previews by default, writes with --apply.
 */
#[AsCommand('app:places:merge-duplicates', 'Merge places recorded under the same external identity into one (preview by default, --apply to write)')]
final class PlacesMergeDuplicatesCommand extends Command
{
    private const int EVENTS_BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly PlaceMetadataRepository $placeMetadataRepository,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the merges. Without it the command only prints what it would do')
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'Only process identities from this external origin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        /** @var string|null $origin */
        $origin = $input->getOption('origin');

        $keys = $this->placeMetadataRepository->findDuplicateKeys($origin);
        if ([] === $keys) {
            $io->success('Every external identity points to a single place.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('%s %d external identities recorded more than once', $apply ? 'Merging' : 'Previewing', \count($keys)));

        $totals = ['placesRemoved' => 0, 'eventsMoved' => 0, 'metadataMoved' => 0, 'metadataDropped' => 0, 'slugsMoved' => 0];

        foreach ($keys as ['externalId' => $externalId, 'externalOrigin' => $externalOrigin]) {
            $places = $this->placeRepository->findAllByExternalIdentity($externalId, $externalOrigin);
            if ([] === $places) {
                continue;
            }

            $eventCounts = $this->eventRepository->countByPlaces(array_map(static fn (Place $place): int => (int) $place->getId(), $places));
            $keeper = $this->chooseKeeper($places, $eventCounts);

            $lines = [];
            foreach ($places as $place) {
                if ($place === $keeper) {
                    continue;
                }

                $merged = $this->mergeInto($keeper, $place, $eventCounts[$place->getId()] ?? 0, $apply);
                $totals['eventsMoved'] += $merged['events'];
                $totals['metadataMoved'] += $merged['metadata'];
                $totals['slugsMoved'] += $merged['slugs'];
                ++$totals['placesRemoved'];

                $lines[] = \sprintf('#%d "%s" (%d events)', $place->getId(), $place->getName(), $merged['events']);
            }

            $dropped = $this->dropRedundantMetadata($keeper, $externalId, $externalOrigin, $apply);
            $totals['metadataDropped'] += $dropped;

            $io->writeln(\sprintf(
                ' <comment>%s@%s</comment> keep #%d "%s" (%d events)%s%s',
                $externalId,
                $externalOrigin,
                $keeper->getId(),
                $keeper->getName(),
                $eventCounts[$keeper->getId()] ?? 0,
                [] === $lines ? '' : ', remove ' . implode(', ', $lines),
                0 === $dropped ? '' : \sprintf(', drop %d redundant row(s)', $dropped),
            ));

            if ($apply) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
        }

        $io->newLine();
        $io->table(
            ['Identities', 'Places removed', 'Events moved', 'Identities moved', 'Redundant rows dropped', 'Name slugs moved'],
            [[\count($keys), $totals['placesRemoved'], $totals['eventsMoved'], $totals['metadataMoved'], $totals['metadataDropped'], $totals['slugsMoved']]],
        );

        if ($apply) {
            $io->success('Merges written.');
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to merge.');
        }

        return Command::SUCCESS;
    }

    /**
     * The place carrying the most events; on a tie the oldest one, which the repository
     * lists first.
     *
     * @param Place[]         $places
     * @param array<int, int> $eventCounts
     */
    private function chooseKeeper(array $places, array $eventCounts): Place
    {
        $keeper = $places[0];
        foreach ($places as $place) {
            if (($eventCounts[$place->getId()] ?? 0) > ($eventCounts[$keeper->getId()] ?? 0)) {
                $keeper = $place;
            }
        }

        return $keeper;
    }

    /**
     * Hand the loser's events, unknown identities and unknown name slugs over to the
     * keeper, then delete it (its remaining rows go with it through cascade remove).
     *
     * @return array{events: int, metadata: int, slugs: int}
     */
    private function mergeInto(Place $keeper, Place $loser, int $eventCount, bool $apply): array
    {
        $eventsMoved = $eventCount;
        if ($apply) {
            $eventsMoved = 0;
            // Small pages re-queried until empty: once moved, an event no longer matches
            while ([] !== ($events = $this->eventRepository->findBy(['place' => $loser], ['id' => 'ASC'], self::EVENTS_BATCH_SIZE))) {
                foreach ($events as $event) {
                    $event->setPlace($keeper);
                    ++$eventsMoved;
                }

                $this->entityManager->flush();
            }
        }

        // Moved rows must leave the loser's collections first: cascade remove would
        // otherwise delete them along with the loser
        $metadataMoved = 0;
        foreach ($loser->getMetadatas()->toArray() as $metadata) {
            if ($keeper->hasMetadata($metadata)) {
                continue;
            }

            $loser->removeMetadata($metadata);
            $keeper->addMetadata($metadata);
            ++$metadataMoved;
        }

        $slugsMoved = 0;
        foreach ($loser->getNameSlugs()->toArray() as $nameSlug) {
            if ($keeper->hasNameSlug((string) $nameSlug->getSlug())) {
                continue;
            }

            $loser->removeNameSlug($nameSlug);
            $keeper->addNameSlug($nameSlug);
            ++$slugsMoved;
        }

        if ($apply) {
            $this->entityManager->remove($loser);
        }

        return ['events' => $eventsMoved, 'metadata' => $metadataMoved, 'slugs' => $slugsMoved];
    }

    /**
     * Collapse an identity recorded several times on the keeper to its oldest row.
     */
    private function dropRedundantMetadata(Place $keeper, string $externalId, string $externalOrigin, bool $apply): int
    {
        $rows = array_values(array_filter(
            $keeper->getMetadatas()->toArray(),
            static fn (PlaceMetadata $metadata): bool => $metadata->getExternalId() === $externalId && $metadata->getExternalOrigin() === $externalOrigin,
        ));
        usort($rows, static fn (PlaceMetadata $a, PlaceMetadata $b): int => $a->getId() <=> $b->getId());
        array_shift($rows);

        foreach ($rows as $redundant) {
            $keeper->removeMetadata($redundant);
            if ($apply) {
                $this->entityManager->remove($redundant);
            }
        }

        return \count($rows);
    }
}
