<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Search\DateRange;
use App\Search\DuplicateSearch;
use DateTimeImmutable;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * The published events a member's event likely repeats, shown before it is published: an organizer often posts on
 * By Night an event its OpenAgenda already brings. Elasticsearch finds the events on one of its dates around its
 * place that share a word of its name (EventElasticaRepository::createDuplicateCandidatesQuery()), EventNameMatcher
 * keeps those whose names say the same event.
 */
final readonly class DuplicateEventFinder
{
    /**
     * How many events Elasticsearch brings back to be judged by their names: the twins came within the first ten of
     * the measures (EventNameMatcher)
     */
    private const int CANDIDATES = 20;

    /**
     * Above that many dates, the period they span is searched instead, each date being a clause of the query
     */
    private const int MAX_DATES = 50;

    /**
     * How far apart two events take place at the same venue: the geocoders place a venue a few hundred metres apart
     */
    private const float SAME_VENUE_KM = 1.0;

    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private EventNameMatcher $eventNameMatcher,
    ) {
    }

    /**
     * @return list<Event> the likeliest first, $limit at most
     */
    public function find(EventDto $dto, int $limit = 5): array
    {
        $search = $this->createSearch($dto);
        if (null === $search) {
            return [];
        }

        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);
        /** @var list<Event> $candidates */
        $candidates = $repository->find($repository->createDuplicateCandidatesQuery($search, self::CANDIDATES));

        $duplicates = [];
        foreach ($candidates as $candidate) {
            if (!$this->isLikelySame($search, $dto, $candidate)) {
                continue;
            }

            $duplicates[] = $candidate;
            if (\count($duplicates) >= $limit) {
                break;
            }
        }

        return $duplicates;
    }

    private function createSearch(EventDto $dto): ?DuplicateSearch
    {
        $name = trim((string) $dto->name);
        $dates = $this->getDates($dto);
        if ('' === $name || [] === $dates) {
            return null;
        }

        $place = $dto->place;
        // The map sets the place's coordinates; a saved event carries its own (EventDtoFactory)
        $latitude = $place?->latitude ?? $dto->latitude;
        $longitude = $place?->longitude ?? $dto->longitude;
        $postalCode = $place?->city?->postalCode;
        $cityName = $place?->city?->name;
        // Nowhere to look around: the same name on the same day anywhere in France is no evidence
        if ((null === $latitude || null === $longitude) && '' === (string) $postalCode && '' === (string) $cityName) {
            return null;
        }

        return new DuplicateSearch(
            name: $name,
            dates: $dates,
            latitude: null !== $longitude ? $latitude : null,
            longitude: null !== $latitude ? $longitude : null,
            postalCode: $postalCode,
            cityName: $cityName,
            excluded: $dto->entityId,
        );
    }

    /**
     * @return list<DateRange>
     */
    private function getDates(EventDto $dto): array
    {
        $dates = [];
        foreach ($dto->timesheets as $timesheet) {
            if (null !== $timesheet->startAt) {
                $dates[] = new DateRange($timesheet->startAt, $timesheet->endAt ?? $timesheet->startAt);
            }
        }

        if ([] === $dates && null !== $dto->startDate) {
            $dates[] = new DateRange($dto->startDate, $dto->endDate ?? $dto->startDate);
        }

        if (\count($dates) > self::MAX_DATES) {
            $from = min(array_map(static fn (DateRange $date): DateTimeImmutable => $date->from, $dates));
            $to = max(array_map(static fn (DateRange $date): DateTimeImmutable => $date->to ?? $date->from, $dates));
            $dates = [new DateRange($from, $to)];
        }

        return $dates;
    }

    private function isLikelySame(DuplicateSearch $search, EventDto $dto, Event $candidate): bool
    {
        $place = $candidate->getPlace();

        return $this->eventNameMatcher->isLikelySame(
            $search->name,
            (string) $candidate->getName(),
            [$dto->place?->name, $dto->place?->city?->name, $place?->getName(), $place?->getCityName()],
            $this->isSameVenue($search, $dto, $candidate),
        );
    }

    private function isSameVenue(DuplicateSearch $search, EventDto $dto, Event $candidate): bool
    {
        $place = $candidate->getPlace();
        if (null !== $dto->place?->entityId && $dto->place->entityId === $place?->getId()) {
            return true;
        }

        $latitude = $place?->getLatitude();
        $longitude = $place?->getLongitude();
        if (!$search->hasCoordinates() || null === $latitude || null === $longitude) {
            return false;
        }

        return self::distance((float) $search->latitude, (float) $search->longitude, $latitude, $longitude) <= self::SAME_VENUE_KM;
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
}
