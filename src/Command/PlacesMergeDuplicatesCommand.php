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
use App\Manager\PlaceMerger;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Utils\PlaceNameNormalizer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Indexes the places under the name slug PlaceNameNormalizer now gives them, then merges the places of a city that
 * have one slug and are one venue.
 *
 * The normalizer used to drop punctuation ("Saint-Honoré-d'Eylau" was "sainthonoredeylau", "Saint-Honoré d'Eylau"
 * "sainthonore deylau") and gave no slug to a place named after its town ("Bobigny" in Bobigny): each spelling, and
 * each "Bobigny", missed the indexed lookup, and the fuzzy fallback, capped at 2,000 places a batch, rarely held the
 * place. The imports made a place of their own of them, 10 to 30 a night.
 *
 * 1. Index: each place gets the slug its own name has now, in its city (or its country, without one), so the next
 *    imports find it. Its other slugs stay: those of a spelling without punctuation did not change.
 * 2. Merge: in each city, the places of one slug that are the same venue (PlaceMerger::isSameVenue()) are merged into
 *    the one with the most events (PlaceMerger): events, source identities, slugs, and their URLs redirecting. The
 *    city-less places are left to app:places:fix-cityless.
 */
#[AsCommand('app:places:merge-duplicates', 'Index the places under their name slug and merge the places of one name that are one venue (preview by default, --apply to write)')]
final class PlacesMergeDuplicatesCommand extends Command
{
    private const int BATCH_SIZE = 5_000;

    /** Merges flush group by group: the entity manager is cleared this often */
    private const int MERGE_BATCH_SIZE = 100;

    private const int PREVIEW_SAMPLE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
        private readonly EventRepository $eventRepository,
        private readonly PlaceMerger $placeMerger,
        private readonly PlaceNameNormalizer $placeNameNormalizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the slugs and the merges. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $io->section(\sprintf('%s the name slugs of the places', $apply ? 'Indexing' : 'Previewing'));
        [$indexed, $groups] = $this->index($io, $apply);

        $io->section(\sprintf('%s the places of one name that are one venue (%d name(s) held by several places of a city)', $apply ? 'Merging' : 'Previewing', \count($groups)));
        $merged = $this->merge($io, $groups, $apply);

        $io->table(
            ['Slugs added', 'Venues', 'Places merged into them', 'Events moved'],
            [[$indexed, $merged['venues'], $merged['places'], $merged['events']]],
        );

        if ($apply) {
            $io->success(\sprintf('%d slug(s) added, %d place(s) merged.', $indexed, $merged['places']));
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to write.');
        }

        return Command::SUCCESS;
    }

    /**
     * Adds the missing slugs, and gathers the places of a city that share one.
     *
     * @return array{int, list<list<int>>} the slugs added, and the ids of each group of places of a city sharing a slug
     */
    private function index(SymfonyStyle $io, bool $apply): array
    {
        $connection = $this->entityManager->getConnection();
        $added = 0;
        $firstOfSlug = [];
        $groups = [];

        $io->progressStart((int) $connection->fetchOne('SELECT COUNT(*) FROM place'));
        $after = 0;
        while ([] !== $places = $connection->fetchAllAssociative(
            'SELECT p.id, p.name, p.city_id, p.country_id, COALESCE(c.name, p.city_name) AS town
            FROM place p
            LEFT JOIN admin_zone c ON c.id = p.city_id
            WHERE p.id > :after
            ORDER BY p.id
            LIMIT ' . self::BATCH_SIZE,
            ['after' => $after],
        )) {
            $after = (int) end($places)['id'];

            $known = [];
            foreach ($connection->fetchAllAssociative(
                'SELECT place_id, slug, city_id, country_id FROM place_name_slug WHERE place_id IN (:ids)',
                ['ids' => array_column($places, 'id')],
                ['ids' => ArrayParameterType::INTEGER],
            ) as $row) {
                $known[self::slugKey($row['place_id'], $row['slug'], $row['city_id'], $row['country_id'])] = true;
            }

            $missing = [];
            foreach ($places as $place) {
                $slug = $this->placeNameNormalizer->normalize($place['name'], $place['town']);
                if (null === $slug) {
                    continue;
                }

                if (!isset($known[self::slugKey($place['id'], $slug, $place['city_id'], $place['country_id'])])) {
                    $missing[] = [$place['id'], $place['city_id'], $place['country_id'], $slug];
                }

                if (null === $place['city_id']) {
                    continue;
                }

                $group = $place['city_id'] . "\0" . $slug;
                if (!isset($firstOfSlug[$group])) {
                    $firstOfSlug[$group] = (int) $place['id'];
                } else {
                    $groups[$group] ??= [$firstOfSlug[$group]];
                    $groups[$group][] = (int) $place['id'];
                }
            }

            if ($apply && [] !== $missing) {
                $connection->executeStatement(
                    'INSERT INTO place_name_slug (place_id, city_id, country_id, slug) VALUES ' . implode(', ', array_fill(0, \count($missing), '(?, ?, ?, ?)')),
                    array_merge(...$missing),
                );
            }

            $added += \count($missing);
            $this->placeNameNormalizer->batchReset();
            $io->progressAdvance(\count($places));
        }

        $io->progressFinish();

        return [$added, array_values($groups)];
    }

    /**
     * @param list<list<int>> $groups
     *
     * @return array{venues: int, places: int, events: int}
     */
    private function merge(SymfonyStyle $io, array $groups, bool $apply): array
    {
        $result = ['venues' => 0, 'places' => 0, 'events' => 0];
        $sample = [];

        $io->progressStart(\count($groups));
        foreach (array_chunk($groups, self::MERGE_BATCH_SIZE) as $batch) {
            foreach ($batch as $ids) {
                /** @var list<Place> $places */
                $places = $this->placeRepository->findBy(['id' => $ids]);
                $eventCounts = $this->eventRepository->countByPlaces($ids);

                foreach ($this->venues($places, $eventCounts) as [$target, $sources]) {
                    ++$result['venues'];
                    $result['places'] += \count($sources);
                    $result['events'] += array_sum(array_map(static fn (Place $source): int => $eventCounts[$source->getId()] ?? 0, $sources));
                    if (\count($sample) < self::PREVIEW_SAMPLE) {
                        $sample[] = [
                            $target->getCity()?->getName(),
                            \sprintf('#%d %s (%s)', $target->getId(), $target->getName(), $target->getStreet() ?? '-'),
                            implode("\n", array_map(static fn (Place $source): string => \sprintf('#%d %s (%s)', $source->getId(), $source->getName(), $source->getStreet() ?? '-'), $sources)),
                        ];
                    }

                    if ($apply) {
                        $this->placeMerger->merge($target, $sources);
                    }
                }

                $io->progressAdvance();
            }

            $this->entityManager->clear();
        }

        $io->progressFinish();
        $io->table(['City', 'Into', 'Merged'], $sample);

        return $result;
    }

    /**
     * The venues among places of one name: the one the others are best merged into (PlaceMerger::suggestTarget())
     * takes those that are the same venue as it and as each one it took, then the next one among the rest. A place
     * without a street is the same venue as a place on any street, but does not make two streets one.
     *
     * @param list<Place>     $places
     * @param array<int, int> $eventCounts the events of each place, by place id
     *
     * @return list<array{Place, non-empty-list<Place>}> each venue's place and the places merged into it
     */
    private function venues(array $places, array $eventCounts): array
    {
        $venues = [];
        while ([] !== $places) {
            $target = $this->placeMerger->suggestTarget($places, $eventCounts);
            $sources = [];
            $others = [];
            foreach ($places as $place) {
                if ($place === $target) {
                    continue;
                }

                if (array_all([$target, ...$sources], fn (Place $venue): bool => $this->placeMerger->isSameVenue($venue, $place))) {
                    $sources[] = $place;
                } else {
                    $others[] = $place;
                }
            }

            if ([] !== $sources) {
                $venues[] = [$target, $sources];
            }

            $places = $others;
        }

        return $venues;
    }

    private static function slugKey(int|string $placeId, string $slug, int|string|null $cityId, ?string $countryId): string
    {
        return implode("\0", [$placeId, $slug, $cityId ?? '', $countryId ?? '']);
    }
}
