<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Place;
use App\Entity\PlaceLegacySlug;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Merges places that name the same venue ("Zénith Toulouse" and "Zénith Toulouse Métropole") into one of them, from
 * the back office: the target takes the events, source identities (so the next imports find it), name slugs and
 * former slugs of the others, which are deleted. Each one's slug is kept as a PlaceLegacySlug in the location it had,
 * so its agenda page redirects to the target's, even from another city.
 *
 * All of it or nothing, in one transaction. The events move through the unit of work, so they are re-indexed, their
 * pages purged and the target's events to come recounted as any change made on the site; the former city of a source
 * in another city is only recounted by the nightly app:events:count-upcoming.
 */
final readonly class PlaceMerger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * The place the others are best merged into: the one with the most events, then the most to come, then the
     * oldest.
     *
     * @param non-empty-list<Place> $places
     * @param array<int, int>       $eventCounts the events of each place, by place id (EventRepository::countByPlaces())
     */
    public function suggestTarget(array $places, array $eventCounts): Place
    {
        usort($places, static fn (Place $a, Place $b): int => [$eventCounts[$b->getId()] ?? 0, $b->getUpcomingEvents(), $a->getId()]
            <=> [$eventCounts[$a->getId()] ?? 0, $a->getUpcomingEvents(), $b->getId()]);

        return $places[0];
    }

    /**
     * @param list<Place> $sources the places merged into $target and deleted
     *
     * @return array{places: int, events: int, identities: int, slugs: int} what moved to the target: the places merged
     *                                                                      into it, their events, source identities and
     *                                                                      slugs (name slugs and former slugs)
     */
    public function merge(Place $target, array $sources): array
    {
        $sources = array_values(array_filter($sources, static fn (Place $source): bool => $source !== $target));
        if ([] === $sources) {
            throw new InvalidArgumentException('Nothing to merge: the places to merge are the target itself.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($target, $sources): array {
            $result = ['places' => \count($sources), 'events' => 0, 'identities' => 0, 'slugs' => 0];
            $legacySlugRepository = $this->entityManager->getRepository(PlaceLegacySlug::class);

            foreach ($sources as $source) {
                foreach ($this->eventRepository->findBy(['place' => $source]) as $event) {
                    $event->setPlace($target);
                    ++$result['events'];
                }

                // An identity is unique across places: none of the target's can be the source's
                foreach ($source->getMetadatas()->toArray() as $metadata) {
                    $source->removeMetadata($metadata);
                    $target->addMetadata($metadata);
                    ++$result['identities'];
                }

                foreach ($source->getNameSlugs()->toArray() as $nameSlug) {
                    $source->removeNameSlug($nameSlug);
                    if ($this->hasNameSlug($target, (string) $nameSlug->getSlug(), $nameSlug->getCity()?->getId(), $nameSlug->getCountry()?->getId())) {
                        $this->entityManager->remove($nameSlug);

                        continue;
                    }

                    $target->addNameSlug($nameSlug);
                    ++$result['slugs'];
                }

                // The places merged into the source earlier now lead to the target
                foreach ($legacySlugRepository->findBy(['place' => $source]) as $legacySlug) {
                    $legacySlug->setPlace($target);
                    ++$result['slugs'];
                }

                if (!$this->isTargetUrl($target, $source)) {
                    $this->entityManager->persist(new PlaceLegacySlug($target, (string) $source->getSlug(), $source->getCity(), $source->getCountry()));
                    ++$result['slugs'];
                }

                $this->completeAddress($target, $source);
                $this->entityManager->remove($source);
            }

            $this->entityManager->flush();

            return $result;
        });
    }

    private function hasNameSlug(Place $place, string $slug, ?int $cityId, ?string $countryId): bool
    {
        foreach ($place->getNameSlugs() as $nameSlug) {
            if ($nameSlug->getSlug() === $slug && $nameSlug->getCity()?->getId() === $cityId && $nameSlug->getCountry()?->getId() === $countryId) {
                return true;
            }
        }

        return false;
    }

    /**
     * The source's agenda URL is already the target's: same slug, same location.
     */
    private function isTargetUrl(Place $target, Place $source): bool
    {
        return $source->getSlug() === $target->getSlug()
            && $source->getCity()?->getId() === $target->getCity()?->getId()
            && (null !== $source->getCity() || $source->getCountry()?->getId() === $target->getCountry()?->getId());
    }

    /**
     * What the target lacks of its address, taken from a source in the same location.
     */
    private function completeAddress(Place $target, Place $source): void
    {
        if ($source->getCity()?->getId() !== $target->getCity()?->getId() || $source->getCountry()?->getId() !== $target->getCountry()?->getId()) {
            return;
        }

        if (null === $target->getStreet() || '' === $target->getStreet()) {
            $target->setStreet($source->getStreet());
        }

        if (null === $target->getLatitude() || null === $target->getLongitude()) {
            $target->setLatitude($source->getLatitude())->setLongitude($source->getLongitude());
        }

        $target->setCityPostalCode($target->getCityPostalCode() ?? $source->getCityPostalCode());
        $target->setCityName($target->getCityName() ?? $source->getCityName());
        $target->setUrl($target->getUrl() ?? $source->getUrl());
        $target->setFacebookId($target->getFacebookId() ?? $source->getFacebookId());
    }
}
