<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Repository\PlaceMetadataRepository;
use App\Repository\PlaceNameSlugRepository;
use App\Repository\PlaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

/**
 * Deletes the places no event points to any more, with their identities and name slugs.
 *
 * A place only comes into being with an event and is left behind when its events are deleted or move elsewhere (an
 * import that starts naming venues, a merge). Such a place still answers on /agenda/sortir-a/{slug} with an empty page
 * and keeps matching new imports by name, so it is better gone. Every place is checked again inside the transaction
 * that deletes it, so an event attached since the list was made saves its place.
 */
final readonly class EventlessPlaceRemover
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlaceRepository $placeRepository,
        private PlaceMetadataRepository $placeMetadataRepository,
        private PlaceNameSlugRepository $placeNameSlugRepository,
    ) {
    }

    /**
     * Removes the given places that still have no event, by batches of one transaction each; with $apply false, only
     * counts what would go.
     *
     * @param list<int> $placeIds places found without an event (PlaceRepository::findEventlessIds())
     *
     * @return Generator<int, array{places: int, identities: int, slugs: int, skipped: int}> the count of each batch;
     *                                                                                       "skipped" got an event meanwhile
     */
    public function remove(array $placeIds, bool $apply): Generator
    {
        // The bulk deletions bypass the unit of work: no place loaded before may be flushed afterwards
        $this->entityManager->clear();

        foreach (array_chunk($placeIds, self::BATCH_SIZE) as $chunk) {
            yield $apply
                ? $this->entityManager->wrapInTransaction(fn (): array => $this->removeBatch($chunk, true))
                : $this->removeBatch($chunk, false);
        }
    }

    /**
     * @param list<int> $chunk
     *
     * @return array{places: int, identities: int, slugs: int, skipped: int}
     */
    private function removeBatch(array $chunk, bool $apply): array
    {
        $ids = $this->placeRepository->onlyEventless($chunk);

        $result = [
            'places' => \count($ids),
            'identities' => $this->placeMetadataRepository->countByPlaces($ids),
            'slugs' => $this->placeNameSlugRepository->countByPlaces($ids),
            'skipped' => \count($chunk) - \count($ids),
        ];

        if ($apply) {
            $this->placeRepository->deleteWithRows($ids);
        }

        return $result;
    }
}
