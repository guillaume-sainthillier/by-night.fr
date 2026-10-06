<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

use App\Import\EventFamilyResolver;
use App\Repository\CrossSourceLinkRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps the cross-source links of the events that are not over in line with what the matcher finds, then has
 * EventFamilyResolver gather or part the events whose links changed.
 *
 * Only the shows whose events all name it alike are linked (CrossSourceGroup::isConsistent()): an inconsistent one is
 * left unlinked as a whole, as a show its sources no longer agree on loses its links. The links of the events already
 * over are kept as they are: an event that ended keeps the page it had.
 */
final readonly class CrossSourceLinker
{
    public function __construct(
        private CrossSourceDuplicateFinder $finder,
        private CrossSourceGrouper $grouper,
        private CrossSourceLinkRepository $linkRepository,
        private EventFamilyResolver $familyResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<int> the venues to scan, for a progress bar
     */
    public function findPlaceIds(DateTimeImmutable $from): array
    {
        return $this->finder->findPlaceIds($from);
    }

    /**
     * @param list<int> $placeIds
     * @param bool      $apply    false to only tell what would change
     *
     * @return iterable<CrossSourceLinkResult> the outcome of each chunk of venues
     */
    public function link(array $placeIds, DateTimeImmutable $from, bool $apply): iterable
    {
        foreach ($this->finder->find($placeIds, $from) as $chunk) {
            $groups = $this->grouper->group($chunk->pairs);

            /** @var array<int, bool> $consistent whether the show of each matched event may be linked */
            $consistent = [];
            foreach ($groups as $group) {
                foreach (array_keys($group->members) as $id) {
                    $consistent[$id] = $group->isConsistent();
                }
            }

            /** @var array<string, array{int, int}> $wanted */
            $wanted = [];
            foreach ($chunk->pairs as $pair) {
                if ($pair->verdict->isMatch() && ($consistent[$pair->leftId] ?? false)) {
                    $wanted[self::key($pair->leftId, $pair->rightId)] = [$pair->leftId, $pair->rightId];
                }
            }

            // The links this scan decides on: both events are at these venues and not over. A pair kept apart is
            // never linked again
            $scope = array_flip($chunk->eventIds);
            /** @var array<string, int> $existing link ids by pair */
            $existing = [];
            foreach ($this->linkRepository->findTouching($chunk->eventIds) as $link) {
                $key = self::key($link['eventId'], $link['linkedEventId']);
                if ($link['keptApart']) {
                    unset($wanted[$key]);
                } elseif (isset($scope[$link['eventId']], $scope[$link['linkedEventId']])) {
                    $existing[$key] = $link['id'];
                }
            }

            $added = array_diff_key($wanted, $existing);
            $removed = array_diff_key($existing, $wanted);

            if ($apply && ([] !== $added || [] !== $removed)) {
                $this->entityManager->wrapInTransaction(function () use ($added, $removed): void {
                    $this->linkRepository->deleteByIds(array_values($removed));
                    foreach ($added as [$leftId, $rightId]) {
                        $this->linkRepository->link($leftId, $rightId);
                    }

                    $this->entityManager->flush();

                    // The events gathered or parted, and through them their families
                    $affected = [];
                    foreach ([...array_values($added), ...array_map(self::pairOf(...), array_keys($removed))] as [$leftId, $rightId]) {
                        $affected[$leftId] = $leftId;
                        $affected[$rightId] = $rightId;
                    }

                    $this->familyResolver->resolveForEvents(array_values($affected));
                });
            }

            yield new CrossSourceLinkResult($chunk, $groups, \count($added), \count($removed));
        }
    }

    /**
     * Part an event from the events it is linked to, for good: someone found them to be other shows.
     *
     * @return list<int> the events it was linked to
     */
    public function keepApart(int $eventId): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($eventId): array {
            $linked = $this->linkRepository->keepApart($eventId);
            $this->entityManager->flush();
            $this->familyResolver->resolveForEvents([$eventId, ...$linked]);

            return $linked;
        });
    }

    private static function key(int $leftId, int $rightId): string
    {
        return min($leftId, $rightId) . '-' . max($leftId, $rightId);
    }

    /**
     * @return array{int, int}
     */
    private static function pairOf(string $key): array
    {
        [$leftId, $rightId] = explode('-', $key);

        return [(int) $leftId, (int) $rightId];
    }
}
