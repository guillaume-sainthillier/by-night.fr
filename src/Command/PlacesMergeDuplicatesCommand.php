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
use App\Entity\PlaceLegacySlug;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Utils\SluggerUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Merges the places of a city recorded more than once: same name and same street, compared as slugs (case, accents
 * and punctuation aside). The DataTourisme feed of June-August 2026 created one place per event for the venues it
 * named after their town (98 "Granville, 1 Rue D'Estouteville"), each under its own identity; members' events made
 * others. The import now matches such places to the existing one: this cleans up what it left.
 *
 * In each group the place with the most events is kept (the oldest on a tie). The others hand it their events (moved
 * through the ORM, so they are reindexed), their identities (the next imports match it directly) and the name slugs
 * it lacks, then are deleted. The kept place takes the shortest slug of the group, the one without a "-1" suffix, and the
 * others are stored (PlaceLegacySlug): the agenda pages they named redirect to its page. Places without a city or a street are left alone: a name alone is not
 * enough to tell two venues apart.
 *
 * Previews by default, writes with --apply, one transaction per city. The stored counts of events to come change with
 * the next run of app:events:count-upcoming.
 */
#[AsCommand('app:places:merge-duplicates', 'Merge the places of a city that share their name and street (preview by default, --apply to write)')]
final class PlacesMergeDuplicatesCommand extends Command
{
    private const int EVENTS_BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the merges. Without it the command only prints what it would do')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'Only the places of this city (id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $cityIds = null !== $input->getOption('city')
            ? [(int) $input->getOption('city')]
            : $this->placeRepository->findCityIdsWithNamesakes();

        $io->section(\sprintf('%s the duplicate places of %d cities', $apply ? 'Merging' : 'Previewing', \count($cityIds)));

        $totals = ['groups' => 0, 'places' => 0, 'events' => 0, 'legacySlugs' => 0];
        $io->progressStart(\count($cityIds));
        foreach ($cityIds as $cityId) {
            $groups = $this->findGroups($cityId);
            if ([] !== $groups) {
                $result = $apply
                    ? $this->entityManager->wrapInTransaction(fn (): array => $this->mergeGroups($groups, true, $io))
                    : $this->mergeGroups($groups, false, $io);

                foreach ($result as $key => $value) {
                    $totals[$key] += $value;
                }

                $this->entityManager->clear();
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->table(
            ['Groups', 'Places removed', 'Events moved', 'Slugs kept for redirects'],
            [[$totals['groups'], $totals['places'], $totals['events'], $totals['legacySlugs']]],
        );

        if ($apply) {
            $io->success(\sprintf('%d place(s) merged into %d. Run app:events:count-upcoming to refresh the counts of events to come.', $totals['places'], $totals['groups']));
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to merge, -v to list the groups.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<list<int>> the ids of the places of the city that share a name and a street, the oldest first
     */
    private function findGroups(int $cityId): array
    {
        $groups = [];
        foreach ($this->placeRepository->findAddressedPlacesOfCity($cityId) as $place) {
            $name = SluggerUtils::generateSlug($place['name']);
            $street = SluggerUtils::generateSlug($place['street']);
            if ('' !== $name && '' !== $street) {
                $groups[$name . '|' . $street][] = $place['id'];
            }
        }

        return array_values(array_filter($groups, static fn (array $ids): bool => \count($ids) > 1));
    }

    /**
     * @param list<list<int>> $groups
     *
     * @return array{groups: int, places: int, events: int, legacySlugs: int}
     */
    private function mergeGroups(array $groups, bool $apply, SymfonyStyle $io): array
    {
        $totals = ['groups' => \count($groups), 'places' => 0, 'events' => 0, 'legacySlugs' => 0];
        $eventCounts = $this->eventRepository->countByPlaces(array_merge(...$groups));

        foreach ($groups as $ids) {
            // The most events, the oldest on a tie: the ids come oldest first and only a greater count replaces
            $keeperId = $ids[0];
            foreach ($ids as $id) {
                if (($eventCounts[$id] ?? 0) > ($eventCounts[$keeperId] ?? 0)) {
                    $keeperId = $id;
                }
            }

            /** @var list<Place> $places */
            $places = $this->placeRepository->findBy(['id' => $ids], ['id' => 'ASC']);
            $keeper = null;
            $losers = [];
            foreach ($places as $place) {
                if ($place->getId() === $keeperId) {
                    $keeper = $place;
                } else {
                    $losers[] = $place;
                }
            }

            \assert($keeper instanceof Place);
            // The URL the group keeps: the shortest slug ("le-bikini" rather than "le-bikini-1"), the oldest on a tie.
            // Its other slugs, the keeper's own included, redirect to it
            $slug = $keeper->getSlug();
            foreach ($places as $place) {
                if (null !== $place->getSlug() && (null === $slug || \strlen($place->getSlug()) < \strlen($slug))) {
                    $slug = $place->getSlug();
                }
            }

            $legacySlugs = array_values(array_unique(array_filter(
                array_map(static fn (Place $place): ?string => $place->getSlug(), $places),
                static fn (?string $placeSlug): bool => null !== $placeSlug && $placeSlug !== $slug,
            )));

            $lines = [];
            foreach ($losers as $loser) {
                $events = $eventCounts[$loser->getId()] ?? 0;
                if ($apply) {
                    $this->mergeInto($keeper, $loser);
                }

                ++$totals['places'];
                $totals['events'] += $events;
                $lines[] = \sprintf('#%d (%d events)', $loser->getId(), $events);
            }

            if ($apply) {
                $keeper->setSlug($slug);
                foreach ($legacySlugs as $legacySlug) {
                    $this->entityManager->persist(new PlaceLegacySlug($keeper, $legacySlug));
                }

                $this->entityManager->flush();
            }

            $totals['legacySlugs'] += \count($legacySlugs);
            if ($io->isVerbose()) {
                $io->writeln(\sprintf(
                    ' keep #%d "%s", %s (%d events) as %s, remove %s%s',
                    $keeper->getId(),
                    $keeper->getName(),
                    $keeper->getStreet(),
                    $eventCounts[$keeperId] ?? 0,
                    $slug,
                    implode(', ', $lines),
                    [] === $legacySlugs ? '' : ', redirect ' . implode(', ', $legacySlugs),
                ));
            }
        }

        return $totals;
    }

    private function mergeInto(Place $keeper, Place $loser): void
    {
        // Small pages re-queried until empty: once moved, an event no longer matches
        while ([] !== ($events = $this->eventRepository->findBy(['place' => $loser], ['id' => 'ASC'], self::EVENTS_BATCH_SIZE))) {
            foreach ($events as $event) {
                $event->setPlace($keeper);
            }

            $this->entityManager->flush();
        }

        // The rows moved must leave the loser's collections first, or cascade remove deletes them with it. An identity
        // is unique, so the keeper cannot already have it; a name slug it already has goes with the loser
        foreach ($loser->getMetadatas()->toArray() as $metadata) {
            $loser->removeMetadata($metadata);
            $keeper->addMetadata($metadata);
        }

        foreach ($loser->getNameSlugs()->toArray() as $nameSlug) {
            if (!$keeper->hasNameSlug((string) $nameSlug->getSlug())) {
                $loser->removeNameSlug($nameSlug);
                $keeper->addNameSlug($nameSlug);
            }
        }

        // The slugs of places merged into the loser before
        foreach ($this->entityManager->getRepository(PlaceLegacySlug::class)->findBy(['place' => $loser]) as $legacySlug) {
            $legacySlug->setPlace($keeper);
        }

        $this->entityManager->remove($loser);
        $this->entityManager->flush();
    }
}
