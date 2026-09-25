<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\ApiResource\SearchResult;
use App\App\LazyLocationFactory;
use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Tag;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What the global search shows before a word is typed, around the city of the visitor when the page knows one: the
 * categories of its events to come, then its top events of the week. Without a city, the biggest cities of France and
 * its top events of the week.
 *
 * @implements ProviderInterface<SearchResult>
 */
final readonly class SearchSuggestionsProvider implements ProviderInterface
{
    private const int CHIPS = 8;
    private const int EVENTS = 5;

    /**
     * The location of a visitor without a city, as the URLs name it.
     */
    private const string DEFAULT_COUNTRY = 'c--france';

    public function __construct(
        private LazyLocationFactory $lazyLocationFactory,
        private EventRepository $eventRepository,
        private CityRepository $cityRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<SearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // A slug the page sends back, which may name a city renamed or merged since: no city then
        $citySlug = $context['filters']['city'] ?? null;
        $location = \is_string($citySlug) && '' !== $citySlug ? $this->lazyLocationFactory->createWithCity($citySlug) : null;

        if (null === $location) {
            return [
                ...$this->cityResults(),
                ...$this->eventResults($this->lazyLocationFactory->createWithLazyCountry(self::DEFAULT_COUNTRY)),
            ];
        }

        return [
            ...$this->categoryResults($location->getCity()),
            ...$this->eventResults($location),
        ];
    }

    /**
     * @return list<SearchResult>
     */
    private function categoryResults(City $city): array
    {
        return array_map(fn (array $row): SearchResult => $this->categoryResult($row[0], $city, (int) $row['events']), $this->eventRepository->findUpcomingCategoriesOfCity($city, self::CHIPS));
    }

    private function categoryResult(Tag $tag, City $city, int $events): SearchResult
    {
        return new SearchResult(
            id: 'tag-' . $tag->getId(),
            type: 'tags',
            category: 'Catégories',
            label: $tag->getName(),
            shortDescription: (string) $events,
            description: null,
            url: $this->urlGenerator->generate('app_agenda_by_tag', [
                'location' => $city->getSlug(),
                'tagSlug' => $tag->getSlug(),
                'tagId' => $tag->getId(),
            ]),
        );
    }

    /**
     * @return list<SearchResult>
     */
    private function cityResults(): array
    {
        return array_map(fn (City $city): SearchResult => new SearchResult(
            id: 'city-' . $city->getId(),
            type: 'cities',
            category: 'Villes',
            label: $city->getName(),
            shortDescription: '',
            description: null,
            url: $this->urlGenerator->generate('app_location_index', ['location' => $city->getSlug()]),
        ), $this->cityRepository->findBiggestOfCountry(self::DEFAULT_COUNTRY, self::CHIPS));
    }

    /**
     * The top events of the week, those to come when the week has none.
     *
     * @return list<SearchResult>
     */
    private function eventResults(Location $location): array
    {
        $group = 'Cette semaine ' . $location->getAtName();
        $events = $this->findEvents($this->eventRepository->findTopEventsQueryBuilder($location));
        if ([] === $events) {
            $group = 'Prochainement ' . $location->getAtName();
            $events = $this->findEvents($this->eventRepository->findUpcomingEvents($location));
        }

        return array_map(fn (Event $event): SearchResult => new SearchResult(
            id: 'event-' . $event->getId(),
            type: 'events',
            category: $group,
            label: (string) $event->getName(),
            shortDescription: $event->getPlace()?->getName() ?? '',
            description: $this->when($event),
            url: $this->urlGenerator->generate('app_event_details', [
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'location' => $event->getLocationSlug(),
            ]),
        ), $events);
    }

    /**
     * The day an event starts, or the day it ends when it runs already: the top events of the week include those that
     * started months ago.
     */
    private function when(Event $event): ?string
    {
        $today = new DateTimeImmutable('today');
        if ($event->getStartDate() < $today && null !== $event->getEndDate()) {
            return 'Jusqu\'au ' . $event->getEndDate()->format('d/m/Y');
        }

        return $event->getStartDate()?->format('d/m/Y');
    }

    /**
     * @return list<Event>
     */
    private function findEvents(QueryBuilder $queryBuilder): array
    {
        // Both queries join the place as "p": its name and its city (the link of the event) come with the event
        return $queryBuilder
            ->addSelect('p', 'suggestion_city')
            ->leftJoin('p.city', 'suggestion_city')
            ->setMaxResults(self::EVENTS)
            ->getQuery()
            ->getResult();
    }
}
