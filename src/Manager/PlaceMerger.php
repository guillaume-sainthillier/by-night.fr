<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Comparator\PlaceComparator;
use App\Entity\Place;
use App\Entity\PlaceLegacySlug;
use App\Repository\EventRepository;
use App\Utils\PlaceNameNormalizer;
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
    /** Two places of one name this close (km) are one venue, whatever their streets */
    private const float SAME_VENUE_DISTANCE = 0.3;

    /** Two places of one name this far apart (km) are two venues, even when one has no street */
    private const float OTHER_VENUE_DISTANCE = 1.0;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private PlaceComparator $placeComparator,
        private PlaceNameNormalizer $placeNameNormalizer,
    ) {
    }

    /**
     * Whether two places of one name in one town are the same venue: on the same street, or within 300 m; else when
     * one of them has no street and nothing says they are apart (more than 1 km). Two "Marseille 11e Arrondissement" at
     * 196 Traverse de la Penne and at 24 Route d'Allauch are two.
     *
     * A place named after its town is somewhere in it, not a venue: only one of the same street, or without one like it,
     * is the same; one without a street is not the one of a street, whose address its events would take ("Paris" and
     * "Paris, avenue Winston-Churchill").
     */
    public function isSameVenue(Place $place, Place $namesake): bool
    {
        $street = (string) $this->placeComparator->sanitizeStreetName($place->getStreet());
        $namesakeStreet = (string) $this->placeComparator->sanitizeStreetName($namesake->getStreet());
        $distance = null === $place->getLatitude() || null === $place->getLongitude() || null === $namesake->getLatitude() || null === $namesake->getLongitude()
            ? null
            : self::distance($place->getLatitude(), $place->getLongitude(), $namesake->getLatitude(), $namesake->getLongitude());

        // Often placed at the town's centre: only its street says where it is
        if ($this->isNamedAfterItsTown($place) || $this->isNamedAfterItsTown($namesake)) {
            return $street === $namesakeStreet && (null === $distance || $distance <= self::OTHER_VENUE_DISTANCE);
        }

        if (('' !== $street && $street === $namesakeStreet) || (null !== $distance && $distance <= self::SAME_VENUE_DISTANCE)) {
            return true;
        }

        return ('' === $street || '' === $namesakeStreet) && (null === $distance || $distance <= self::OTHER_VENUE_DISTANCE);
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

    private function isNamedAfterItsTown(Place $place): bool
    {
        $town = $place->getCity()?->getName() ?? $place->getCityName();

        return null !== $town && $this->placeNameNormalizer->normalize($place->getName(), $town) === $this->placeNameNormalizer->normalize($town);
    }

    /**
     * The great-circle distance between two points, in kilometres.
     */
    private static function distance(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        $latitudeDelta = deg2rad($otherLatitude - $latitude);
        $longitudeDelta = deg2rad($otherLongitude - $longitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 2 * 6371 * asin(min(1.0, sqrt($haversine)));
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
