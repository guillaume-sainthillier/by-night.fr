<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

use App\Entity\Event;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the shows two sources import at the same venue, among the events that are not over. Read only: it tells
 * which pairs CrossSourceMatcher takes for one show, it links nothing.
 *
 * Venue by venue, the titles are compared on plain rows first (hundreds of thousands of pairs share a venue and a
 * few days), and only the pairs whose titles agree are loaded with their sessions for the full match.
 */
final readonly class CrossSourceDuplicateFinder
{
    private const int PLACES_PER_CHUNK = 200;

    public function __construct(
        private EventRepository $eventRepository,
        private EventTitleComparator $titleComparator,
        private CrossSourceMatcher $matcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<int> the venues to scan, for a progress bar
     */
    public function findPlaceIds(DateTimeImmutable $from): array
    {
        return $this->eventRepository->findPlaceIdsListedBySeveralSources($from);
    }

    /**
     * The pairs whose titles agree, with the verdict of the full match (their sessions may still keep them apart).
     *
     * @param list<int> $placeIds
     *
     * @return iterable<int, list<CrossSourcePair>> the pairs of each chunk of venues, keyed by the number of venues scanned
     */
    public function find(array $placeIds, DateTimeImmutable $from): iterable
    {
        foreach (array_chunk($placeIds, self::PLACES_PER_CHUNK) as $chunk) {
            $candidates = [];
            $rowsByPlace = [];
            foreach ($this->eventRepository->findImportedRowsAtPlaces($chunk, $from) as $row) {
                $rowsByPlace[$row['placeId']][] = $row;
            }

            foreach ($rowsByPlace as $rows) {
                foreach ($rows as $i => $left) {
                    foreach (\array_slice($rows, $i + 1) as $right) {
                        if ($left['fromData'] === $right['fromData'] || !self::rangesOverlap($left, $right)) {
                            continue;
                        }

                        $title = $this->titleComparator->compare((string) $left['name'], (string) $right['name'], [$left['placeName'], $left['cityName']]);
                        if ($title->isMatch()) {
                            $candidates[] = [$left, $right];
                        }
                    }
                }
            }

            yield \count($chunk) => $this->matchAll($candidates);

            $this->entityManager->clear();
        }
    }

    /**
     * @param list<array{0: array{id: int, fromData: string, name: string|null, startDate: DateTimeImmutable|null, placeName: string|null, cityName: string|null}, 1: array{id: int, fromData: string, name: string|null}}> $candidates
     *
     * @return list<CrossSourcePair>
     */
    private function matchAll(array $candidates): array
    {
        $ids = [];
        foreach ($candidates as [$left, $right]) {
            $ids[$left['id']] = $left['id'];
            $ids[$right['id']] = $right['id'];
        }

        $events = [];
        foreach ($this->eventRepository->findWithTimesheets(array_values($ids)) as $event) {
            $events[(int) $event->getId()] = $event;
        }

        $pairs = [];
        foreach ($candidates as [$left, $right]) {
            $leftEvent = $events[$left['id']] ?? null;
            $rightEvent = $events[$right['id']] ?? null;
            if (!$leftEvent instanceof Event || !$rightEvent instanceof Event) {
                continue;
            }

            $pairs[] = new CrossSourcePair(
                $left['id'],
                $left['fromData'],
                (string) $left['name'],
                $right['id'],
                $right['fromData'],
                (string) $right['name'],
                (string) $left['placeName'],
                $left['cityName'],
                $left['startDate'],
                $this->matcher->match($leftEvent, $rightEvent),
            );
        }

        return $pairs;
    }

    /**
     * @param array{startDate: DateTimeImmutable|null, endDate: DateTimeImmutable|null} $left
     * @param array{startDate: DateTimeImmutable|null, endDate: DateTimeImmutable|null} $right
     */
    private static function rangesOverlap(array $left, array $right): bool
    {
        if (null === $left['startDate'] || null === $right['startDate']) {
            return false;
        }

        return $left['startDate'] <= ($right['endDate'] ?? $right['startDate'])
            && $right['startDate'] <= ($left['endDate'] ?? $left['startDate']);
    }
}
